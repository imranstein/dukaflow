<?php

declare(strict_types=1);

use App\Modules\Distribution\Enums\DayOfWeek;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\VisitSchedule;

/*
 * The order of a day's round is the whole value of the sequence column. A
 * round served in row order sends the rep back and forth across the city, and
 * nothing about the result would look wrong.
 */

it('returns the round in the order the rep walks it', function () {
    $last = Customer::factory()->create(['name' => 'Third stop']);
    $first = Customer::factory()->create(['name' => 'First stop']);
    $middle = Customer::factory()->create(['name' => 'Second stop']);

    // Created in the wrong order on purpose: row order must not decide this.
    VisitSchedule::factory()->on(DayOfWeek::Monday, sequence: 30)->create(['customer_id' => $last->id]);
    VisitSchedule::factory()->on(DayOfWeek::Monday, sequence: 10)->create(['customer_id' => $first->id]);
    VisitSchedule::factory()->on(DayOfWeek::Monday, sequence: 20)->create(['customer_id' => $middle->id]);

    $round = Customer::query()->scheduledOn(DayOfWeek::Monday)->pluck('name')->all();

    expect($round)->toBe(['First stop', 'Second stop', 'Third stop']);
});

it('orders each day by that day own sequence', function () {
    $a = Customer::factory()->create(['name' => 'Outlet A']);
    $b = Customer::factory()->create(['name' => 'Outlet B']);

    // A is called on first on Monday and second on Thursday.
    VisitSchedule::factory()->on(DayOfWeek::Monday, sequence: 1)->create(['customer_id' => $a->id]);
    VisitSchedule::factory()->on(DayOfWeek::Monday, sequence: 2)->create(['customer_id' => $b->id]);
    VisitSchedule::factory()->on(DayOfWeek::Thursday, sequence: 2)->create(['customer_id' => $a->id]);
    VisitSchedule::factory()->on(DayOfWeek::Thursday, sequence: 1)->create(['customer_id' => $b->id]);

    expect(Customer::query()->scheduledOn(DayOfWeek::Monday)->pluck('name')->all())
        ->toBe(['Outlet A', 'Outlet B'])
        ->and(Customer::query()->scheduledOn(DayOfWeek::Thursday)->pluck('name')->all())
        ->toBe(['Outlet B', 'Outlet A']);
});

it('is stable when two outlets share a position', function () {
    $earlier = Customer::factory()->create(['name' => 'Earlier record']);
    $later = Customer::factory()->create(['name' => 'Later record']);

    VisitSchedule::factory()->on(DayOfWeek::Friday, sequence: 5)->create(['customer_id' => $later->id]);
    VisitSchedule::factory()->on(DayOfWeek::Friday, sequence: 5)->create(['customer_id' => $earlier->id]);

    $rounds = collect(range(1, 4))
        ->map(fn (): array => Customer::query()->scheduledOn(DayOfWeek::Friday)->pluck('name')->all())
        ->unique()
        ->values()
        ->all();

    expect($rounds)->toBe([['Earlier record', 'Later record']]);
});
