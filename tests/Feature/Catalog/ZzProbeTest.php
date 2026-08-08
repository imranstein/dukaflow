<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\PriceResolver;
use Illuminate\Support\Carbon;

function probeList(Product $product, string $price, array $state = []): PriceList
{
    $list = PriceList::factory()->create($state);
    PriceListItem::factory()->pricedAt($price)->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    return $list;
}

it('probe: same effective_from, older list created first', function () {
    $product = Product::factory()->create();
    $old = probeList($product, '30.00', ['effective_from' => Carbon::today()]);
    $new = probeList($product, '27.00', ['effective_from' => Carbon::today()]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $old->id]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $new->id]);

    dump('created old-then-new => '.(new PriceResolver)->priceFor($product->id, customerId: 42)?->toDecimal());
})->skip(false);

it('probe: same effective_from, newer list created first', function () {
    $product = Product::factory()->create();
    $new = probeList($product, '27.00', ['effective_from' => Carbon::today()]);
    $old = probeList($product, '30.00', ['effective_from' => Carbon::today()]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $old->id]);
    PriceListAssignment::factory()->forCustomer(42)->create(['price_list_id' => $new->id]);

    dump('created new-then-old => '.(new PriceResolver)->priceFor($product->id, customerId: 42)?->toDecimal());
});

it('probe: two defaults same effective_from', function () {
    $product = Product::factory()->create();
    probeList($product, '30.00', ['is_default' => true, 'effective_from' => Carbon::today()]);
    probeList($product, '27.00', ['is_default' => true, 'effective_from' => Carbon::today()]);

    dump('two defaults => '.(new PriceResolver)->priceFor($product->id)?->toDecimal());
});

it('probe: scope vs isEffectiveOn agreement across timezones', function () {
    $product = Product::factory()->create();
    $list = probeList($product, '30.00', [
        'is_default' => true,
        'effective_from' => Carbon::today(),
        'effective_to' => Carbon::today(),
    ]);

    $tzDate = Carbon::parse(Carbon::today()->toDateString().' 09:00:00', 'Africa/Addis_Ababa');

    $inScope = PriceList::query()->whereKey($list->id)->effectiveOn($tzDate)->exists();
    $viaModel = $list->fresh()->isEffectiveOn($tzDate);

    dump([
        'app_tz' => config('app.timezone'),
        'stored_from' => PriceList::query()->whereKey($list->id)->value('effective_from'),
        'scope_says' => $inScope,
        'model_says' => $viaModel,
        'resolver_says' => (new PriceResolver)->priceFor($product->id, on: $tzDate)?->toDecimal(),
    ]);
});

it('probe: effective_to boundary with a mid-day datetime', function () {
    $product = Product::factory()->create();
    $list = probeList($product, '30.00', [
        'is_default' => true,
        'effective_from' => Carbon::today()->subDay(),
        'effective_to' => Carbon::today(),
    ]);

    dump([
        'today midday' => (new PriceResolver)->priceFor($product->id, on: Carbon::today()->setTime(15, 30))?->toDecimal(),
        'isEffectiveOn midday' => $list->isEffectiveOn(Carbon::today()->setTime(15, 30)),
        'tomorrow' => (new PriceResolver)->priceFor($product->id, on: Carbon::tomorrow())?->toDecimal(),
    ]);
});
