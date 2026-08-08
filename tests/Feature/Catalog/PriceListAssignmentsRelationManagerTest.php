<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Enums\PriceListScope;
use App\Modules\Catalog\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Modules\Catalog\Filament\Resources\PriceLists\RelationManagers\AssignmentsRelationManager;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use Filament\Actions\Testing\TestAction;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * This screen is where the module boundary shows up in the interface: the
 * options come from Distribution through a shared-kernel contract, not from
 * its models. The table also enforces one assignment per list per target, so
 * the form has to say so before the database does.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

/** @return Testable<AssignmentsRelationManager> */
function assignmentsManager(PriceList $priceList): Testable
{
    return Livewire::test(AssignmentsRelationManager::class, [
        'ownerRecord' => $priceList,
        'pageClass' => EditPriceList::class,
    ]);
}

it('attaches a price list to an outlet', function () {
    $priceList = PriceList::factory()->create();
    $outlet = Customer::factory()->create();

    assignmentsManager($priceList)
        ->callAction(TestAction::make('create')->table(), data: [
            'scope' => PriceListScope::Customer->value,
            'scope_id' => $outlet->id,
        ])
        ->assertHasNoActionErrors();

    $assignment = PriceListAssignment::query()->sole();

    expect($assignment->scope)->toBe(PriceListScope::Customer)
        ->and($assignment->scope_id)->toBe($outlet->id);
});

it('refuses to attach the same list to the same outlet twice', function () {
    $priceList = PriceList::factory()->create();
    $outlet = Customer::factory()->create();
    PriceListAssignment::factory()->forCustomer($outlet->id)->create(['price_list_id' => $priceList->id]);

    assignmentsManager($priceList)
        ->callAction(TestAction::make('create')->table(), data: [
            'scope' => PriceListScope::Customer->value,
            'scope_id' => $outlet->id,
        ])
        ->assertHasActionErrors(['scope_id']);

    expect(PriceListAssignment::query()->count())->toBe(1);
});

it('allows an outlet and a route that happen to share an id', function () {
    $priceList = PriceList::factory()->create();
    $outlet = Customer::factory()->create();
    PriceListAssignment::factory()->forCustomer($outlet->id)->create(['price_list_id' => $priceList->id]);

    $route = Route::factory()->create();

    assignmentsManager($priceList)
        ->callAction(TestAction::make('create')->table(), data: [
            'scope' => PriceListScope::Route->value,
            'scope_id' => $route->id,
        ])
        ->assertHasNoActionErrors();

    expect(PriceListAssignment::query()->count())->toBe(2);
});

it('lets the same outlet sit on two different lists', function () {
    $first = PriceList::factory()->create();
    $second = PriceList::factory()->create();
    $outlet = Customer::factory()->create();

    PriceListAssignment::factory()->forCustomer($outlet->id)->create(['price_list_id' => $first->id]);

    assignmentsManager($second)
        ->callAction(TestAction::make('create')->table(), data: [
            'scope' => PriceListScope::Customer->value,
            'scope_id' => $outlet->id,
        ])
        ->assertHasNoActionErrors();

    expect(PriceListAssignment::query()->count())->toBe(2);
});

it('names the outlet without Catalog knowing what an outlet is', function () {
    $priceList = PriceList::factory()->create();
    $outlet = Customer::factory()->create(['name' => 'Medhanialem Mini Market']);
    PriceListAssignment::factory()->forCustomer($outlet->id)->create(['price_list_id' => $priceList->id]);

    assignmentsManager($priceList)->assertSee('Medhanialem Mini Market');
});

it('shows only this list assignments', function () {
    $mine = PriceList::factory()->create();
    $other = PriceList::factory()->create();

    $ours = PriceListAssignment::factory()->forCustomer(1)->create(['price_list_id' => $mine->id]);
    $theirs = PriceListAssignment::factory()->forCustomer(2)->create(['price_list_id' => $other->id]);

    assignmentsManager($mine)
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});
