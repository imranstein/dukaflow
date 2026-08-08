<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<PriceList> */
class PriceListFactory extends Factory
{
    /** @var class-string<PriceList> */
    protected $model = PriceList::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('PL-???')),
            'name' => fake()->words(2, true).' price list',
            'currency' => 'ETB',
            'effective_from' => Carbon::today()->subMonth(),
            'effective_to' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): self
    {
        return $this->state(['is_default' => true]);
    }

    public function effectiveBetween(Carbon $from, ?Carbon $to = null): self
    {
        return $this->state(['effective_from' => $from, 'effective_to' => $to]);
    }

    public function expired(): self
    {
        return $this->state([
            'effective_from' => Carbon::today()->subYear(),
            'effective_to' => Carbon::today()->subDay(),
        ]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
