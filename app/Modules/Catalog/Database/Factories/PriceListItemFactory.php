<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceListItem> */
class PriceListItemFactory extends Factory
{
    /** @var class-string<PriceListItem> */
    protected $model = PriceListItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'product_id' => Product::factory(),
            'unit_price_minor' => fake()->numberBetween(1_00, 900_00),
        ];
    }

    public function pricedAt(string $decimal): self
    {
        return $this->state(['unit_price_minor' => Money::fromDecimal($decimal)->minorUnits]);
    }
}
