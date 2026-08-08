<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Modules\Catalog\Filament\Resources\PriceLists\RelationManagers\ItemsRelationManager;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use Filament\Actions\Testing\TestAction;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * People type "1450.00"; the column stores 145000. These cover the conversion
 * in both directions, which is the part of the form that can silently be off
 * by a factor of a hundred.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

/** @return Testable<ItemsRelationManager> */
function itemsManager(PriceList $priceList): Testable
{
    return Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $priceList,
        'pageClass' => EditPriceList::class,
    ]);
}

it('stores a typed decimal price as minor units', function () {
    $priceList = PriceList::factory()->create(['currency' => 'ETB']);
    $product = Product::factory()->create();

    itemsManager($priceList)
        ->callAction(TestAction::make('create')->table(), data: [
            'product_id' => $product->id,
            'unit_price_minor' => '1450.00',
        ])
        ->assertHasNoActionErrors();

    expect(PriceListItem::query()->sole()->unit_price_minor)->toBe(145000);
});

it('keeps sub-unit precision', function () {
    $priceList = PriceList::factory()->create();
    $product = Product::factory()->create();

    itemsManager($priceList)
        ->callAction(TestAction::make('create')->table(), data: [
            'product_id' => $product->id,
            'unit_price_minor' => '0.07',
        ])
        ->assertHasNoActionErrors();

    expect(PriceListItem::query()->sole()->unit_price_minor)->toBe(7);
});

it('shows a stored price back as a decimal', function () {
    $priceList = PriceList::factory()->create();
    $item = PriceListItem::factory()->pricedAt('312.00')->create(['price_list_id' => $priceList->id]);

    itemsManager($priceList)
        ->mountAction(TestAction::make('edit')->table($item))
        ->assertActionDataSet(['unit_price_minor' => '312.00']);
});

it('rejects an amount with more precision than the currency has', function () {
    $priceList = PriceList::factory()->create();
    $product = Product::factory()->create();

    itemsManager($priceList)
        ->callAction(TestAction::make('create')->table(), data: [
            'product_id' => $product->id,
            'unit_price_minor' => '12.505',
        ])
        ->assertHasActionErrors(['unit_price_minor']);

    expect(PriceListItem::query()->count())->toBe(0);
});

it('refuses a second price for the same product on one list', function () {
    $priceList = PriceList::factory()->create();
    $product = Product::factory()->create();
    PriceListItem::factory()->pricedAt('10.00')->create([
        'price_list_id' => $priceList->id,
        'product_id' => $product->id,
    ]);

    itemsManager($priceList)
        ->callAction(TestAction::make('create')->table(), data: [
            'product_id' => $product->id,
            'unit_price_minor' => '11.00',
        ])
        ->assertHasActionErrors(['product_id']);

    expect(PriceListItem::query()->count())->toBe(1);
});

it('lists only the prices on this list', function () {
    $priceList = PriceList::factory()->create();
    $other = PriceList::factory()->create();

    $mine = PriceListItem::factory()->pricedAt('5.00')->create(['price_list_id' => $priceList->id]);
    $theirs = PriceListItem::factory()->pricedAt('6.00')->create(['price_list_id' => $other->id]);

    itemsManager($priceList)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
