<?php

declare(strict_types=1);

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Support\DistributionDirectory;
use App\Support\Contracts\ScopeDirectory;
use App\Support\NullScopeDirectory;

/*
 * The seam that lets Catalog attach a price list to an outlet without
 * depending on Distribution. If this binding breaks, the price list screens
 * quietly offer an empty dropdown rather than failing, so it is worth an
 * explicit test.
 */

it('resolves to the directory Distribution provides', function () {
    expect(app(ScopeDirectory::class))->toBeInstanceOf(DistributionDirectory::class);
});

it('names outlets and routes', function () {
    $outlet = Customer::factory()->create(['name' => 'Medhanialem Mini Market']);
    $route = Route::factory()->create(['name' => 'Bole beat']);

    $directory = app(ScopeDirectory::class);

    expect($directory->options('customer'))->toBe([$outlet->id => 'Medhanialem Mini Market'])
        ->and($directory->options('route'))->toBe([$route->id => 'Bole beat'])
        ->and($directory->label('customer', $outlet->id))->toBe('Medhanialem Mini Market')
        ->and($directory->label('route', $route->id))->toBe('Bole beat');
});

it('sorts options by name', function () {
    Customer::factory()->create(['name' => 'Zewditu Shop']);
    Customer::factory()->create(['name' => 'Abebe Kiosk']);

    expect(array_values(app(ScopeDirectory::class)->options('customer')))
        ->toBe(['Abebe Kiosk', 'Zewditu Shop']);
});

it('says which kinds it knows about', function () {
    $directory = app(ScopeDirectory::class);

    expect($directory->handles('customer'))->toBeTrue()
        ->and($directory->handles('route'))->toBeTrue()
        ->and($directory->handles('warehouse'))->toBeFalse()
        ->and($directory->options('warehouse'))->toBe([]);
});

it('returns nothing for a record that has been deleted', function () {
    $outlet = Customer::factory()->create();
    $id = $outlet->id;
    $outlet->delete();

    expect(app(ScopeDirectory::class)->label('customer', $id))->toBeNull();
});

it('falls back to a directory that knows nothing', function () {
    $null = new NullScopeDirectory;

    expect($null->handles('customer'))->toBeFalse()
        ->and($null->options('customer'))->toBe([])
        ->and($null->label('customer', 1))->toBeNull();
});
