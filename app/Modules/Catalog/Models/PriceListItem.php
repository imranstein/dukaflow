<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\PriceListItemFactory;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $price_list_id
 * @property int $product_id
 * @property int $unit_price_minor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PriceList $priceList
 * @property-read Product $product
 */
class PriceListItem extends Model
{
    /** @use HasFactory<PriceListItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['price_list_id', 'product_id', 'unit_price_minor'];

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The currency belongs to the parent list, so reading a price touches the
     * relation. Eager load `priceList` when reading many of these at once.
     */
    public function unitPrice(): Money
    {
        return Money::ofMinor($this->unit_price_minor, $this->priceList->currency);
    }

    /** @return Factory<PriceListItem> */
    protected static function newFactory(): Factory
    {
        return PriceListItemFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unit_price_minor' => 'integer'];
    }
}
