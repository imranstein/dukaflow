<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockReconciliationFactory;
use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\ReconciliationStatus;
use App\Modules\Inventory\Services\StockLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A day's count of one rep's van.
 *
 * @property int $id
 * @property int $sales_rep_id
 * @property Carbon $reconciled_on
 * @property ReconciliationStatus $status
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property-read Collection<int, StockReconciliationLine> $lines
 */
class StockReconciliation extends Model
{
    /** @use HasFactory<StockReconciliationFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = ['status' => 'open'];

    /** @var list<string> */
    protected $fillable = ['sales_rep_id', 'reconciled_on', 'notes'];

    /** @return HasMany<StockReconciliationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockReconciliationLine::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ReconciliationStatus::Open;
    }

    /**
     * The lines where the count disagreed with the ledger.
     *
     * @return Collection<int, StockReconciliationLine>
     */
    public function variances(): Collection
    {
        return $this->lines->filter(
            fn (StockReconciliationLine $line): bool => $line->variance() !== 0
        );
    }

    public function hasVariance(): bool
    {
        return $this->variances()->isNotEmpty();
    }

    /**
     * Accepts the count and makes the books agree with it.
     *
     * This is the only place in the application that writes adjustments
     * automatically, and each one references this reconciliation, so the
     * trail runs from "the ledger said 12, we counted 11" to the row that
     * settled it.
     */
    public function close(StockLedger $ledger): self
    {
        if (! $this->isOpen()) {
            throw new LogicException("Reconciliation {$this->id} is already closed.");
        }

        return DB::transaction(function () use ($ledger): self {
            // Re-read under the lock. Closing twice from two instances of the
            // same row would otherwise apply every adjustment twice.
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReconciliationStatus::Open) {
                throw new LogicException("Reconciliation {$this->id} is already closed.");
            }

            foreach ($this->lines as $line) {
                // Against the ledger as it stands now, not the number written
                // on the count sheet. A sale landing between drawing the sheet
                // and closing it would otherwise leave the books disagreeing
                // with the count, which is the one thing this must not do.
                $onLedger = $ledger->balance($line->product_id, LocationType::Van, $this->sales_rep_id);
                $variance = $line->counted_quantity - $onLedger;

                if ($variance === 0) {
                    continue;
                }

                $ledger->adjust(
                    productId: $line->product_id,
                    locationType: LocationType::Van,
                    locationId: $this->sales_rep_id,
                    quantity: $variance,
                    reason: sprintf(
                        'End of day count on %s: ledger said %d, counted %d.',
                        $this->reconciled_on->toDateString(),
                        $onLedger,
                        $line->counted_quantity,
                    ),
                    on: $this->reconciled_on,
                    referenceType: 'reconciliation',
                    referenceId: $this->id,
                );
            }

            $this->status = ReconciliationStatus::Closed;
            $this->closed_at = Carbon::now();
            $this->save();

            return $this;
        });
    }

    /** @return Factory<StockReconciliation> */
    protected static function newFactory(): Factory
    {
        return StockReconciliationFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'reconciled_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }
}
