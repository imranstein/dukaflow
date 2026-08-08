<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    /** @var class-string<Warehouse> */
    protected $model = Warehouse::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('WH-##')),
            'name' => fake()->randomElement(['Bole', 'Kality', 'Gerji', 'Lebu']).' depot',
            'address' => fake()->randomElement(['Bole', 'Kality', 'Gerji']).', Addis Ababa',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): self
    {
        return $this->state(['is_default' => true]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
