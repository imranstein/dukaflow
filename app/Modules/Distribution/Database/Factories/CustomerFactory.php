<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Database\Factories;

use App\Modules\Distribution\Enums\OutletType;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    /** @var class-string<Customer> */
    protected $model = Customer::class;

    /** Roughly the bounding box of Addis Ababa. */
    private const LATITUDE = [8.87, 9.12];

    private const LONGITUDE = [38.65, 38.89];

    /** @var list<string> */
    private const OWNERS = [
        'Almaz Bekele', 'Getachew Alemu', 'Sara Haile', 'Mulu Teshome',
        'Kidus Mengistu', 'Genet Fikru', 'Samuel Worku', 'Aster Gebre',
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('CUS-####')),
            'name' => fake()->randomElement(self::OWNERS).' Shop',
            'outlet_type' => fake()->randomElement(OutletType::cases()),
            'owner_name' => fake()->randomElement(self::OWNERS),
            'phone' => '+2519'.fake()->numerify('########'),
            'address' => fake()->randomElement(['Bole', 'Piassa', 'Merkato', 'Gerji']).', Addis Ababa',
            'latitude' => (string) fake()->randomFloat(7, self::LATITUDE[0], self::LATITUDE[1]),
            'longitude' => (string) fake()->randomFloat(7, self::LONGITUDE[0], self::LONGITUDE[1]),
            'route_id' => null,
            'is_active' => true,
        ];
    }

    public function onRoute(Route|int $route): self
    {
        return $this->state([
            'route_id' => $route instanceof Route ? $route->id : $route,
        ]);
    }

    /** An outlet registered before anyone captured its position. */
    public function withoutLocation(): self
    {
        return $this->state(['latitude' => null, 'longitude' => null]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
