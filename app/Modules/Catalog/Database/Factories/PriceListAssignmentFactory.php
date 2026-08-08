<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\PriceListScope;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceListAssignment> */
class PriceListAssignmentFactory extends Factory
{
    /** @var class-string<PriceListAssignment> */
    protected $model = PriceListAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'scope' => PriceListScope::Customer,
            'scope_id' => fake()->numberBetween(1, 1000),
        ];
    }

    public function forCustomer(int $customerId): self
    {
        return $this->state(['scope' => PriceListScope::Customer, 'scope_id' => $customerId]);
    }

    public function forRoute(int $routeId): self
    {
        return $this->state(['scope' => PriceListScope::Route, 'scope_id' => $routeId]);
    }
}
