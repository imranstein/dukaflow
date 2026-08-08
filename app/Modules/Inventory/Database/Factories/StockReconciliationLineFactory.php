<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Inventory\Models\StockReconciliationLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockReconciliationLine> */
class StockReconciliationLineFactory extends Factory
{
    /** @var class-string<StockReconciliationLine> */
    protected $model = StockReconciliationLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $expected = fake()->numberBetween(0, 60);

        return [
            'stock_reconciliation_id' => StockReconciliation::factory(),
            'product_id' => fake()->numberBetween(1, 200),
            'expected_quantity' => $expected,
            'counted_quantity' => $expected,
        ];
    }

    public function counted(int $productId, int $expected, int $counted): self
    {
        return $this->state([
            'product_id' => $productId,
            'expected_quantity' => $expected,
            'counted_quantity' => $counted,
        ]);
    }
}
