<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockMovementFactory;
use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of the stock ledger.
 *
 * Append-only, and enforced rather than merely documented: the model refuses
 * to be updated or deleted. A mistake is corrected by recording another
 * movement, because a ledger you can edit is not evidence.
 *
 * @property int $id
 * @property int $product_id
 * @property LocationType $location_type
 * @property int $location_id
 * @property int $quantity
 * @property MovementType $type
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property Carbon $occurred_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /** There is no update to stamp on a row that never changes. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'location_type',
        'location_id',
        'quantity',
        'type',
        'reference_type',
        'reference_id',
        'occurred_at',
        'notes',
    ];

    /** @param  Builder<StockMovement>  $query */
    public function scopeAt(Builder $query, LocationType $locationType, int $locationId): void
    {
        $query->where('location_type', $locationType)->where('location_id', $locationId);
    }

    /** @param  Builder<StockMovement>  $query */
    public function scopeForProduct(Builder $query, int $productId): void
    {
        $query->where('product_id', $productId);
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'The stock ledger is append-only. Correct a mistake by recording another movement.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'The stock ledger is append-only. A movement cannot be deleted.'
            );
        });
    }

    /** @return Factory<StockMovement> */
    protected static function newFactory(): Factory
    {
        return StockMovementFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'location_type' => LocationType::class,
            'type' => MovementType::class,
            'quantity' => 'integer',
            'location_id' => 'integer',
            'product_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
