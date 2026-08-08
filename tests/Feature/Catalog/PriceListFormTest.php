<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Modules\Catalog\Models\PriceList;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Money accepts a three letter code and throws on anything else, so a bad
 * currency stored here would not fail at the point of the mistake — it would
 * fail later, on every screen that renders a price from this list.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function priceListInput(array $overrides = []): array
{
    return array_merge([
        'code' => 'PL-TEST',
        'name' => 'Test price list',
        'currency' => 'ETB',
        'effective_from' => Carbon::today()->toDateString(),
        'effective_to' => null,
        'is_default' => false,
        'is_active' => true,
    ], $overrides);
}

it('creates a price list', function () {
    Livewire::test(CreatePriceList::class)
        ->fillForm(priceListInput())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PriceList::query()->sole()->currency)->toBe('ETB');
});

it('rejects a currency Money cannot understand', function (string $currency) {
    Livewire::test(CreatePriceList::class)
        ->fillForm(priceListInput(['currency' => $currency]))
        ->call('create')
        ->assertHasFormErrors(['currency']);

    expect(PriceList::query()->count())->toBe(0);
})->with([
    'four letters' => ['BIRR'],
    'two letters' => ['ET'],
    'digits' => ['123'],
    'mixed' => ['E1B'],
]);

it('stores the currency uppercase so Money never has to guess', function () {
    Livewire::test(CreatePriceList::class)
        ->fillForm(priceListInput(['currency' => 'kes']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PriceList::query()->sole()->currency)->toBe('KES');
});

it('refuses a list that stops before it starts', function () {
    Livewire::test(CreatePriceList::class)
        ->fillForm(priceListInput([
            'effective_from' => Carbon::today()->toDateString(),
            'effective_to' => Carbon::today()->subDay()->toDateString(),
        ]))
        ->call('create')
        ->assertHasFormErrors(['effective_to']);
});

it('accepts a list that starts and stops on the same day', function () {
    Livewire::test(CreatePriceList::class)
        ->fillForm(priceListInput([
            'effective_from' => Carbon::today()->toDateString(),
            'effective_to' => Carbon::today()->toDateString(),
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PriceList::query()->count())->toBe(1);
});

it('refuses to reuse a code', function () {
    PriceList::factory()->create(['code' => 'PL-TEST']);

    Livewire::test(CreatePriceList::class)
        ->fillForm(priceListInput())
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('keeps every currency it stores readable by Money', function () {
    Livewire::test(CreatePriceList::class)
        ->fillForm(priceListInput(['currency' => 'kes']))
        ->call('create');

    $list = PriceList::query()->sole();

    // The whole point: this must not throw.
    expect(Money::ofMinor(1250, $list->currency)->format())->toBe('KES 12.50');
});
