<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    /** @var class-string<Product> */
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'sku' => mb_strtoupper(fake()->unique()->bothify('???-####')),
            'name' => fake()->words(3, true),

            // A product with no selling unit is the exception, not the rule.
            // Leaving it null by default meant nothing in the suite ever
            // exercised the relation, including the column that renders it.
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'pack_size' => fake()->randomElement([6, 12, 20, 24, 48]),
            'category' => fake()->randomElement(['Water', 'Soft drinks', 'Staples', 'Household']),
            'barcode' => fake()->unique()->numerify('##############'),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /** An older record from before the catalogue captured units. */
    public function withoutUnit(): self
    {
        return $this->state(['unit_of_measure_id' => null, 'pack_size' => 1]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
