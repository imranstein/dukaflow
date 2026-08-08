<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Database\Factories;

use App\Modules\Distribution\Enums\DayOfWeek;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\VisitSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisitSchedule> */
class VisitScheduleFactory extends Factory
{
    /** @var class-string<VisitSchedule> */
    protected $model = VisitSchedule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'sequence' => fake()->numberBetween(1, 30),
            'is_active' => true,
        ];
    }

    public function on(DayOfWeek $day, int $sequence = 1): self
    {
        return $this->state(['day_of_week' => $day, 'sequence' => $sequence]);
    }
}
