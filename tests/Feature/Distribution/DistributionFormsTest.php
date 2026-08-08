<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Filament\Resources\UnitOfMeasures\Pages\CreateUnitOfMeasure;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Distribution\Enums\OutletType;
use App\Modules\Distribution\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Modules\Distribution\Filament\Resources\Routes\Pages\CreateRoute;
use App\Modules\Distribution\Filament\Resources\SalesReps\Pages\CreateSalesRep;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Every one of these codes is unique in the database. Without a matching rule
 * on the form, a second one does not come back as a message on the field — it
 * comes back as an unhandled QueryException.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

it('refuses a duplicate outlet code', function () {
    Customer::factory()->create(['code' => 'CUS-0001']);

    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'code' => 'CUS-0001',
            'name' => 'Duplicate outlet',
            'outlet_type' => OutletType::Kiosk->value,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);

    expect(Customer::query()->count())->toBe(1);
});

it('refuses a duplicate route code', function () {
    Route::factory()->create(['code' => 'RT-01']);

    Livewire::test(CreateRoute::class)
        ->fillForm(['code' => 'RT-01', 'name' => 'Duplicate beat', 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['code']);

    expect(Route::query()->count())->toBe(1);
});

it('refuses a duplicate rep code', function () {
    SalesRep::factory()->create(['code' => 'REP-01']);

    Livewire::test(CreateSalesRep::class)
        ->fillForm(['code' => 'REP-01', 'name' => 'Duplicate rep', 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['code']);

    expect(SalesRep::query()->count())->toBe(1);
});

it('refuses a duplicate unit code', function () {
    UnitOfMeasure::factory()->create(['code' => 'CTN']);

    Livewire::test(CreateUnitOfMeasure::class)
        ->fillForm(['code' => 'CTN', 'name' => 'Duplicate carton', 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['code']);

    expect(UnitOfMeasure::query()->count())->toBe(1);
});

it('refuses to hand one login to two reps', function () {
    $user = User::factory()->create();
    SalesRep::factory()->create(['user_id' => $user->id]);

    Livewire::test(CreateSalesRep::class)
        ->fillForm(['code' => 'REP-99', 'name' => 'Second rep', 'user_id' => $user->id, 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['user_id']);

    expect(SalesRep::query()->count())->toBe(1);
});

it('stores codes uppercase', function () {
    Livewire::test(CreateRoute::class)
        ->fillForm(['code' => 'rt-09', 'name' => 'Lowercase beat', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Route::query()->sole()->code)->toBe('RT-09');
});

it('rejects coordinates that are not on the planet', function (string $field, string $value) {
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'code' => 'CUS-9999',
            'name' => 'Impossible outlet',
            'outlet_type' => OutletType::Kiosk->value,
            'is_active' => true,
            $field => $value,
        ])
        ->call('create')
        ->assertHasFormErrors([$field]);
})->with([
    'latitude too high' => ['latitude', '91'],
    'latitude too low' => ['latitude', '-91'],
    'longitude too high' => ['longitude', '181'],
    'longitude too low' => ['longitude', '-181'],
]);

it('accepts an outlet in Addis Ababa', function () {
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'code' => 'CUS-9998',
            'name' => 'Bole outlet',
            'outlet_type' => OutletType::Supermarket->value,
            'latitude' => '9.0320000',
            'longitude' => '38.7469000',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Customer::query()->sole()->hasLocation())->toBeTrue();
});
