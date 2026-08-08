<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property int|null $unit_of_measure_id
 * @property int $pack_size
 * @property string|null $category
 * @property string|null $barcode
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UnitOfMeasure|null $unitOfMeasure
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'sku',
        'name',
        'unit_of_measure_id',
        'pack_size',
        'category',
        'barcode',
        'description',
        'is_active',
    ];

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /** @return HasMany<PriceListItem, $this> */
    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    /** @param  Builder<Product>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Models outside app/Models do not resolve their factory by convention,
     * so the module points at its own.
     *
     * @return Factory<Product>
     */
    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pack_size' => 'integer',
        ];
    }
}
