<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Database\Factories;

use App\Models\User;
use App\Modules\Distribution\Models\SalesRep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesRep> */
class SalesRepFactory extends Factory
{
    /** @var class-string<SalesRep> */
    protected $model = SalesRep::class;

    /** @var list<string> */
    private const NAMES = [
        'Dawit Tesfaye', 'Meron Abebe', 'Yohannes Girma', 'Tigist Mulugeta',
        'Solomon Kebede', 'Hana Wolde', 'Henok Assefa', 'Rahel Desta',
        'Abel Tadesse', 'Bethlehem Negash',
    ];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'code' => mb_strtoupper(fake()->unique()->bothify('REP-##')),
            'name' => fake()->randomElement(self::NAMES),
            'phone' => '+2519'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }

    public function withLogin(): self
    {
        return $this->state(fn (): array => ['user_id' => User::factory()]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
