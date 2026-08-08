<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Database\Factories\OrderFactory;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderTransitionException;
use App\Support\Events\OrderFulfilled;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * @property int $id
 * @property string $reference
 * @property int $customer_id
 * @property int|null $sales_rep_id
 * @property int|null $route_id
 * @property int|null $price_list_id
 * @property OrderStatus $status
 * @property string $currency
 * @property int $total_minor
 * @property string|null $notes
 * @property Carbon $placed_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property-read Collection<int, OrderLine> $lines
 * @property-read Collection<int, OrderPayment> $payments
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = [
        'status' => 'draft',
        'total_minor' => '0',
    ];

    /** @var list<string> */
    protected $fillable = [
        'reference',
        'customer_id',
        'sales_rep_id',
        'route_id',
        'price_list_id',
        'currency',
        'notes',
        'placed_at',
    ];

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return HasMany<OrderPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function total(): Money
    {
        return Money::ofMinor($this->total_minor, $this->currency);
    }

    public function amountPaid(): Money
    {
        $paid = (int) $this->payments()->sum('amount_minor');

        return Money::ofMinor($paid, $this->currency);
    }

    /** What the outlet still owes. Never negative: an overpayment reads as settled. */
    public function balance(): Money
    {
        $outstanding = $this->total_minor - $this->amountPaid()->minorUnits;

        return Money::ofMinor(max(0, $outstanding), $this->currency);
    }

    public function isSettled(): bool
    {
        return $this->balance()->isZero();
    }

    /**
     * Rewrites the stored total from the lines.
     *
     * Called by whatever changes a line. The total is stored so that order
     * lists do not each carry a subquery, which leaves it able to drift; the
     * suite asserts it never does.
     */
    public function recalculateTotal(): self
    {
        $this->total_minor = (int) $this->lines()->sum('line_total_minor');
        $this->save();

        return $this;
    }

    public function submit(): self
    {
        if ($this->lines()->count() === 0) {
            throw OrderTransitionException::cannotSubmitWithoutLines();
        }

        return $this->transitionTo(OrderStatus::Submitted, 'submitted_at');
    }

    public function approve(): self
    {
        return $this->transitionTo(OrderStatus::Approved, 'approved_at');
    }

    /**
     * The goods have left. Inventory hears about it through a domain event
     * and writes the stock movements; this module does not know it exists.
     *
     * The status change and whatever the listeners do share one transaction.
     * A van that turns out to be short throws, and the order stays where it
     * was — "fulfilled" with no stock movement behind it would be a lie the
     * ledger could never explain.
     */
    public function fulfil(): self
    {
        return DB::transaction(function (): self {
            $this->transitionTo(OrderStatus::Fulfilled, 'fulfilled_at');

            Event::dispatch(new OrderFulfilled(
                orderId: $this->id,
                reference: $this->reference,
                salesRepId: $this->sales_rep_id,
                occurredAt: $this->fulfilled_at ?? Carbon::now(),
                quantities: $this->lines()->pluck('quantity', 'product_id')->all(),
            ));

            return $this;
        });
    }

    public function cancel(?string $reason = null): self
    {
        $this->cancellation_reason = $reason;

        return $this->transitionTo(OrderStatus::Cancelled, 'cancelled_at');
    }

    public function assertLinesAreEditable(): void
    {
        if (! $this->status->allowsEditingLines()) {
            throw OrderTransitionException::linesAreFrozen($this->status);
        }
    }

    /** @param  Builder<Order>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            OrderStatus::Draft,
            OrderStatus::Submitted,
            OrderStatus::Approved,
        ]);
    }

    /** @param  Builder<Order>  $query */
    public function scopePlacedOn(Builder $query, Carbon $date): void
    {
        $query->whereDate('placed_at', $date);
    }

    private function transitionTo(OrderStatus $next, string $stampedAt): self
    {
        if (! $this->status->canMoveTo($next)) {
            throw OrderTransitionException::cannotMove($this->status, $next);
        }

        $this->status = $next;
        $this->{$stampedAt} = Carbon::now();
        $this->save();

        return $this;
    }

    /** @return Factory<Order> */
    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_minor' => 'integer',
            'placed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
