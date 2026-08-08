<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Database\Factories\OrderLineFactory;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One product on an order, at the price and under the name agreed at the time.
 *
 * The sku, name and unit are copies, not lookups. See
 * Docs/adr/0005-order-lifecycle.md for why an order must not follow the
 * catalogue as it changes.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_sku
 * @property string $product_name
 * @property string|null $unit_code
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property int|null $price_list_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 */
class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'product_id',
        'product_sku',
        'product_name',
        'unit_code',
        'quantity',
        'unit_price_minor',
        'line_total_minor',
        'price_list_id',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function unitPrice(): Money
    {
        return Money::ofMinor($this->unit_price_minor, $this->order->currency);
    }

    public function lineTotal(): Money
    {
        return Money::ofMinor($this->line_total_minor, $this->order->currency);
    }

    /**
     * Keeps the line total honest. Quantities here are whole cases and
     * crates, so the multiplication is exact.
     */
    public static function totalFor(int $unitPriceMinor, int $quantity): int
    {
        return Money::ofMinor($unitPriceMinor)->multipliedBy($quantity)->minorUnits;
    }

    /** @return Factory<OrderLine> */
    protected static function newFactory(): Factory
    {
        return OrderLineFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }
}
