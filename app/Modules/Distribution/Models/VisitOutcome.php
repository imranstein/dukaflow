<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Models;

use App\Modules\Distribution\Database\Factories\VisitOutcomeFactory;
use App\Modules\Distribution\Enums\VisitOutcomeType;
use App\Modules\Distribution\Exceptions\VisitOutcomeException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What actually happened on a call, written once by the device that captured
 * it and never edited after — see Docs/adr/0002-offline-sync-strategy.md §3.
 *
 * @property int $id
 * @property string|null $client_id
 * @property int $customer_id
 * @property int $sales_rep_id
 * @property int|null $route_id
 * @property VisitOutcomeType $outcome
 * @property string|null $reason
 * @property int|null $order_id
 * @property string|null $order_reference
 * @property Carbon $occurred_at
 * @property Carbon|null $received_at
 * @property-read Customer $customer
 * @property-read SalesRep $salesRep
 * @property-read Route|null $route
 */
class VisitOutcome extends Model
{
    /** @use HasFactory<VisitOutcomeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'customer_id',
        'sales_rep_id',
        'route_id',
        'outcome',
        'reason',
        'order_id',
        'order_reference',
        'occurred_at',
        'received_at',
    ];

    /**
     * The one way one of these gets written. A plain create() would let a
     * no-sale through with nothing explaining it, which is a call that never
     * happened as far as a manager reviewing the round can tell.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(array $attributes): self
    {
        $outcome = $attributes['outcome'] instanceof VisitOutcomeType
            ? $attributes['outcome']
            : VisitOutcomeType::from((string) $attributes['outcome']);

        if ($outcome->requiresReason() && empty($attributes['reason'])) {
            throw VisitOutcomeException::reasonRequired();
        }

        return self::query()->create($attributes);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SalesRep, $this> */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    /** @return BelongsTo<Route, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /** @return Factory<VisitOutcome> */
    protected static function newFactory(): Factory
    {
        return VisitOutcomeFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'outcome' => VisitOutcomeType::class,
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
