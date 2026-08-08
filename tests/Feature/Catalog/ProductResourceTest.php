<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * This is the walking skeleton for the module conventions described in
 * docs/adr/0001-module-boundaries.md. Between them these tests prove that a
 * module's migration, factory and Filament resource are all discovered, none
 * of which Laravel or Filament does by default for code outside app/Models
 * and app/Filament.
 */

beforeEach(function (): void {
    actingAs(User::factory()->create());
});

it('discovers the module migration', function () {
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasColumns('products', ['sku', 'name', 'description', 'is_active']))->toBeTrue();
});

it('resolves the factory that lives inside the module', function () {
    $product = Product::factory()->create(['name' => 'Ambo Mineral Water 1L']);

    expect($product->exists)->toBeTrue()
        ->and($product->name)->toBe('Ambo Mineral Water 1L');
});

it('registers the module resource with the admin panel', function () {
    Product::factory()->create(['name' => 'Ambo Mineral Water 1L']);

    get(ListProducts::getUrl())->assertOk();
});

it('lists products created in the module', function () {
    $listed = Product::factory()->create(['name' => 'Ambo Mineral Water 1L']);
    $alsoListed = Product::factory()->create(['name' => 'Moha Soft Drink 300ml']);

    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords([$listed, $alsoListed]);
});
