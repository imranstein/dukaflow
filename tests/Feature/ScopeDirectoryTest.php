<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\PriceResolver;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Support\CompositeScopeDirectory;
use App\Support\Contracts\Pricebook;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;

/*
 * The seam that lets one module name another's records, and price against
 * another's rules, without depending on it. If a binding breaks, the screens
 * quietly offer empty dropdowns rather than failing, so it is worth pinning.
 */

it('assembles one directory from every module', function () {
    expect(app(ScopeDirectory::class))->toBeInstanceOf(CompositeScopeDirectory::class)
        ->and(app(ScopeDirectory::class))->toBe(app(CompositeScopeDirectory::class));
});

it('answers for the scopes both modules contribute', function () {
    $directory = app(ScopeDirectory::class);

    expect($directory->handles(Scope::Customer->value))->toBeTrue()
        ->and($directory->handles(Scope::Route->value))->toBeTrue()
        ->and($directory->handles(Scope::SalesRep->value))->toBeTrue()
        ->and($directory->handles(Scope::Product->value))->toBeTrue()
        ->and($directory->handles(Scope::PriceList->value))->toBeTrue()
        ->and($directory->handles('something-nobody-owns'))->toBeFalse();
});

it('names records from Distribution', function () {
    $outlet = Customer::factory()->create(['name' => 'Medhanialem Mini Market']);
    $route = Route::factory()->create(['name' => 'Bole beat']);
    $rep = SalesRep::factory()->create(['name' => 'Dawit Tesfaye']);

    $directory = app(ScopeDirectory::class);

    expect($directory->label(Scope::Customer->value, $outlet->id))->toBe('Medhanialem Mini Market')
        ->and($directory->label(Scope::Route->value, $route->id))->toBe('Bole beat')
        ->and($directory->label(Scope::SalesRep->value, $rep->id))->toBe('Dawit Tesfaye');
});

it('names records from Catalog', function () {
    $product = Product::factory()->create(['sku' => 'AMB-W-1000', 'name' => 'Ambo Mineral Water 1L']);
    $list = PriceList::factory()->create(['name' => 'Standard trade price list']);

    $directory = app(ScopeDirectory::class);

    expect($directory->label(Scope::Product->value, $product->id))->toBe('Ambo Mineral Water 1L')
        ->and($directory->label(Scope::PriceList->value, $list->id))->toBe('Standard trade price list')
        ->and($directory->options(Scope::Product->value))->toBe([$product->id => 'AMB-W-1000 — Ambo Mineral Water 1L']);
});

it('leaves inactive products out of the pickable options', function () {
    $stocked = Product::factory()->create(['sku' => 'AAA-0001', 'name' => 'Still sold']);
    Product::factory()->inactive()->create(['name' => 'Discontinued']);

    expect(app(ScopeDirectory::class)->options(Scope::Product->value))
        ->toBe([$stocked->id => 'AAA-0001 — Still sold']);
});

it('sorts options by name', function () {
    Customer::factory()->create(['name' => 'Zewditu Shop']);
    Customer::factory()->create(['name' => 'Abebe Kiosk']);

    expect(array_values(app(ScopeDirectory::class)->options(Scope::Customer->value)))
        ->toBe(['Abebe Kiosk', 'Zewditu Shop']);
});

it('returns nothing for a record that has been deleted', function () {
    $outlet = Customer::factory()->create();
    $id = $outlet->id;
    $outlet->delete();

    expect(app(ScopeDirectory::class)->label(Scope::Customer->value, $id))->toBeNull();
});

it('answers nothing for a scope no module owns', function () {
    $directory = app(ScopeDirectory::class);

    expect($directory->options('warehouse-from-the-future'))->toBe([])
        ->and($directory->label('warehouse-from-the-future', 1))->toBeNull();
});

it('resolves pricing through the contract rather than the module', function () {
    expect(app(Pricebook::class))->toBeInstanceOf(PriceResolver::class);
});

it('quotes a price and the list it came from without exposing a model', function () {
    $product = Product::factory()->create();
    $list = PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);
    $list->items()->create(['product_id' => $product->id, 'unit_price_minor' => 31200]);

    $pricebook = app(Pricebook::class);

    expect($pricebook->priceFor($product->id)?->toDecimal())->toBe('312.00')
        ->and($pricebook->priceListIdFor($product->id))->toBe($list->id)
        ->and($pricebook->priceFor($product->id, customerId: 999)?->currency)->toBe('ETB');
});
