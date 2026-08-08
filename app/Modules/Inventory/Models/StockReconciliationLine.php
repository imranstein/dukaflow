<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Database\Factories\StockReconciliationLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_reconciliation_id
 * @property int $product_id
 * @property int $expected_quantity
 * @property int $counted_quantity
 * @property-read StockReconciliation $stockReconciliation
 */
class StockReconciliationLine extends Model
{
    /** @use HasFactory<StockReconciliationLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'stock_reconciliation_id',
        'product_id',
        'expected_quantity',
        'counted_quantity',
    ];

    /** @return BelongsTo<StockReconciliation, $this> */
    public function stockReconciliation(): BelongsTo
    {
        return $this->belongsTo(StockReconciliation::class);
    }

    /**
     * Counted minus expected. Negative means stock is missing, which is the
     * common case and usually the interesting one.
     */
    public function variance(): int
    {
        return $this->counted_quantity - $this->expected_quantity;
    }

    public function isShort(): bool
    {
        return $this->variance() < 0;
    }

    /** @return Factory<StockReconciliationLine> */
    protected static function newFactory(): Factory
    {
        return StockReconciliationLineFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'counted_quantity' => 'integer',
        ];
    }
}
