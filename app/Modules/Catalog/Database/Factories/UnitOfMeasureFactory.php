<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitOfMeasure> */
class UnitOfMeasureFactory extends Factory
{
    /** @var class-string<UnitOfMeasure> */
    protected $model = UnitOfMeasure::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->word(),
            'is_active' => true,
        ];
    }

    public function piece(): self
    {
        return $this->state(['code' => 'PCS', 'name' => 'Piece']);
    }

    public function carton(): self
    {
        return $this->state(['code' => 'CTN', 'name' => 'Carton']);
    }
}
