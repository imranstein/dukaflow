<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Database\Factories;

use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Route> */
class RouteFactory extends Factory
{
    /** @var class-string<Route> */
    protected $model = Route::class;

    /** Neighbourhoods a beat would be named after. */
    private const AREAS = [
        'Bole', 'Piassa', 'Merkato', 'Kazanchis', 'Megenagna', 'Sarbet',
        'Gerji', 'Ayat', 'Lideta', 'Kirkos',
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $area = fake()->randomElement(self::AREAS);

        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('RT-##')),
            'name' => $area.' beat',
            'description' => 'Outlets around '.$area.'.',
            'sales_rep_id' => null,
            'is_active' => true,
        ];
    }

    public function withRep(): self
    {
        return $this->state(fn (): array => ['sales_rep_id' => SalesRep::factory()]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
