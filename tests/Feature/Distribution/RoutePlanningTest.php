<?php

declare(strict_types=1);

use App\Modules\Distribution\Enums\DayOfWeek;
use App\Modules\Distribution\Enums\OutletType;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitSchedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

it('puts a rep on a route and outlets on that route', function () {
    $rep = SalesRep::factory()->create(['name' => 'Dawit Tesfaye']);
    $route = Route::factory()->create(['sales_rep_id' => $rep->id, 'name' => 'Bole beat']);
    Customer::factory()->count(3)->onRoute($route)->create();

    expect($route->salesRep?->name)->toBe('Dawit Tesfaye')
        ->and($route->customers)->toHaveCount(3)
        ->and($rep->routes->pluck('name')->all())->toBe(['Bole beat']);
});

it('finds the outlets due on a given day', function () {
    $dueToday = Customer::factory()->create(['name' => 'Almaz Kiosk']);
    $dueTomorrow = Customer::factory()->create(['name' => 'Getachew Supermarket']);

    VisitSchedule::factory()->on(DayOfWeek::Tuesday, sequence: 1)->create(['customer_id' => $dueToday->id]);
    VisitSchedule::factory()->on(DayOfWeek::Wednesday, sequence: 1)->create(['customer_id' => $dueTomorrow->id]);

    $tuesday = Customer::query()->scheduledOn(DayOfWeek::Tuesday)->pluck('name');

    expect($tuesday->all())->toBe(['Almaz Kiosk']);
});

it('leaves out a schedule that has been switched off', function () {
    $customer = Customer::factory()->create();
    VisitSchedule::factory()
        ->on(DayOfWeek::Friday)
        ->create(['customer_id' => $customer->id, 'is_active' => false]);

    expect(Customer::query()->scheduledOn(DayOfWeek::Friday)->count())->toBe(0);
});

it('lets one outlet be called on more than once a week', function () {
    $customer = Customer::factory()->create();
    VisitSchedule::factory()->on(DayOfWeek::Monday, sequence: 4)->create(['customer_id' => $customer->id]);
    VisitSchedule::factory()->on(DayOfWeek::Thursday, sequence: 9)->create(['customer_id' => $customer->id]);

    expect($customer->visitSchedules()->count())->toBe(2)
        ->and(Customer::query()->scheduledOn(DayOfWeek::Monday)->count())->toBe(1)
        ->and(Customer::query()->scheduledOn(DayOfWeek::Thursday)->count())->toBe(1);
});

it('refuses two schedules for the same outlet on the same day', function () {
    $customer = Customer::factory()->create();
    VisitSchedule::factory()->on(DayOfWeek::Monday)->create(['customer_id' => $customer->id]);

    VisitSchedule::factory()->on(DayOfWeek::Monday)->create(['customer_id' => $customer->id]);
})->throws(UniqueConstraintViolationException::class);

it('maps a date to the same day numbering the schedule uses', function () {
    // 2026-08-10 is a Monday.
    expect(DayOfWeek::for(Carbon::parse('2026-08-10')))->toBe(DayOfWeek::Monday)
        ->and(DayOfWeek::for(Carbon::parse('2026-08-16')))->toBe(DayOfWeek::Sunday)
        ->and(DayOfWeek::Monday->value)->toBe(1)
        ->and(DayOfWeek::Sunday->value)->toBe(7);
});

it('keeps the outlet position when one is captured', function () {
    $located = Customer::factory()->create(['latitude' => '9.0320000', 'longitude' => '38.7469000']);
    $unlocated = Customer::factory()->withoutLocation()->create();

    expect($located->hasLocation())->toBeTrue()
        ->and((float) $located->latitude)->toBe(9.032)
        ->and($unlocated->hasLocation())->toBeFalse();
});

it('casts the outlet type to an enum', function () {
    $customer = Customer::factory()->create(['outlet_type' => OutletType::Wholesaler]);

    expect($customer->refresh()->outlet_type)->toBe(OutletType::Wholesaler)
        ->and($customer->outlet_type->label())->toBe('Wholesaler');
});

it('detaches outlets when a route is deleted rather than losing them', function () {
    $route = Route::factory()->create();
    $customer = Customer::factory()->onRoute($route)->create();

    $route->delete();

    expect($customer->refresh()->route_id)->toBeNull()
        ->and($customer->exists)->toBeTrue();
});

it('removes the schedule when an outlet is deleted', function () {
    $customer = Customer::factory()->create();
    VisitSchedule::factory()->on(DayOfWeek::Monday)->create(['customer_id' => $customer->id]);

    $customer->delete();

    expect(VisitSchedule::query()->count())->toBe(0);
});
