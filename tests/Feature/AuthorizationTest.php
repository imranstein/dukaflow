<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Filament\Resources\Customers\Pages\ListCustomers;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Three roles: admins do everything, managers maintain the data, reps read.
 * The same policy backs every back-office model, so these run across a model
 * from each module to prove the registration in both service providers works.
 */

dataset('back office models', [
    'product' => [Product::class],
    'price list' => [PriceList::class],
    'customer' => [Customer::class],
    'route' => [Route::class],
]);

it('lets every role read', function (string $model) {
    foreach (UserRole::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);
        $record = $model::factory()->create();

        expect($user->can('viewAny', $model))->toBeTrue()
            ->and($user->can('view', $record))->toBeTrue();
    }
})->with('back office models');

it('stops a rep changing anything', function (string $model) {
    $rep = User::factory()->rep()->create();
    $record = $model::factory()->create();

    expect($rep->can('create', $model))->toBeFalse()
        ->and($rep->can('update', $record))->toBeFalse()
        ->and($rep->can('delete', $record))->toBeFalse();
})->with('back office models');

it('lets a manager create and update but not delete', function (string $model) {
    $manager = User::factory()->manager()->create();
    $record = $model::factory()->create();

    expect($manager->can('create', $model))->toBeTrue()
        ->and($manager->can('update', $record))->toBeTrue()
        ->and($manager->can('delete', $record))->toBeFalse();
})->with('back office models');

it('lets an admin delete', function (string $model) {
    $admin = User::factory()->admin()->create();
    $record = $model::factory()->create();

    expect($admin->can('create', $model))->toBeTrue()
        ->and($admin->can('update', $record))->toBeTrue()
        ->and($admin->can('delete', $record))->toBeTrue();
})->with('back office models');

it('opens the panel to every role', function () {
    foreach (UserRole::cases() as $role) {
        actingAs(User::factory()->create(['role' => $role]));

        get(ListProducts::getUrl())->assertOk();
        get(ListCustomers::getUrl())->assertOk();
    }
});

it('turns anonymous visitors away from the panel', function () {
    get(ListProducts::getUrl())->assertRedirect();
});

it('will not take a role from mass assignment', function () {
    // The role is the only attribute that decides what a user may do. If it
    // were fillable, any future form that took user input straight into
    // create() or update() would hand out administrator.
    $user = new User;
    $user->fill(['name' => 'Opportunist', 'email' => 'x@dukaflow.test', 'role' => UserRole::Admin]);

    expect($user->getAttributes())->not->toHaveKey('role')
        ->and(User::factory()->create()->fresh()->role)->toBe(UserRole::Rep);
});

it('describes each role', function () {
    expect(UserRole::Admin->label())->toBe('Administrator')
        ->and(UserRole::Rep->label())->toBe('Sales rep')
        ->and(UserRole::Manager->canWriteBackOffice())->toBeTrue()
        ->and(UserRole::Manager->canDeleteRecords())->toBeFalse()
        ->and(UserRole::Rep->canWriteBackOffice())->toBeFalse()
        ->and(UserRole::Admin->canDeleteRecords())->toBeTrue();
});
