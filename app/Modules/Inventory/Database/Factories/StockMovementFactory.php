<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    /** @var class-string<StockMovement> */
    protected $model = StockMovement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => fake()->numberBetween(1, 200),
            'location_type' => LocationType::Warehouse,
            'location_id' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 100),
            'type' => MovementType::Receipt,
            'occurred_at' => Carbon::now(),
        ];
    }

    public function inWarehouse(int $warehouseId): self
    {
        return $this->state(['location_type' => LocationType::Warehouse, 'location_id' => $warehouseId]);
    }

    public function onVan(int $salesRepId): self
    {
        return $this->state(['location_type' => LocationType::Van, 'location_id' => $salesRepId]);
    }

    public function of(int $productId, int $quantity): self
    {
        return $this->state(['product_id' => $productId, 'quantity' => $quantity]);
    }
}
