<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitOutcome;
use App\Modules\Orders\Models\Order;
use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncConflict;
use App\Modules\Sync\Models\SyncDevice;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/*
 * The push contract from Docs/adr/0002-offline-sync-strategy.md §2-3: a new
 * client_id is submitted, a repeated one replays, a reused one with
 * different content conflicts rather than overwriting anything — and, per
 * §8, a device only gets to act for customers on its own rep's route.
 */

/** @return array{0: User, 1: SalesRep} */
function repUser(): array
{
    $user = User::factory()->rep()->create();
    $rep = SalesRep::factory()->create(['user_id' => $user->id]);

    return [$user, $rep];
}

/** A customer this rep actually covers — required since submitting for one costs an ownership check. */
function customerFor(SalesRep $rep): Customer
{
    $route = Route::factory()->create(['sales_rep_id' => $rep->id]);

    return Customer::factory()->create(['route_id' => $route->id]);
}

/**
 * @param  list<array<string, mixed>>  $entities
 * @return array<string, mixed>
 */
function pushPayload(array $entities, ?string $deviceId = null): array
{
    return [
        'device_id' => $deviceId ?? (string) Str::ulid(),
        'device_label' => 'Test Device',
        'entities' => $entities,
    ];
}

/** @return array<string, mixed> */
function orderEntity(int $customerId, int $productId, int $unitPriceMinor, ?int $priceListId, ?string $clientId = null): array
{
    return [
        'client_id' => $clientId ?? (string) Str::ulid(),
        'entity_type' => 'order',
        'data' => [
            'customer_id' => $customerId,
            'route_id' => null,
            'currency' => 'ETB',
            'placed_at' => now()->toIso8601String(),
            'lines' => [
                ['product_id' => $productId, 'quantity' => 2, 'unit_price_minor' => $unitPriceMinor, 'price_list_id' => $priceListId],
            ],
        ],
    ];
}

it('rejects a push with no session', function () {
    postJson('/api/sync/push', pushPayload([]))->assertUnauthorized();
});

it('rejects a user with no sales rep record', function () {
    actingAs(User::factory()->admin()->create());

    $customer = Customer::factory()->create();
    $entity = orderEntity($customer->id, Product::factory()->create()->id, 5000, null);

    postJson('/api/sync/push', pushPayload([$entity]))->assertForbidden();
});

it('submits a new order and puts it in the approval queue', function () {
    [$user, $rep] = repUser();
    actingAs($user);

    $customer = customerFor($rep);
    $list = PriceList::factory()->default()->create(['currency' => 'ETB']);
    $product = Product::factory()->create();
    PriceListItem::factory()->for($list, 'priceList')->pricedAt('50.00')->create(['product_id' => $product->id]);

    $response = postJson('/api/sync/push', pushPayload([
        orderEntity($customer->id, $product->id, 5000, $list->id),
    ]));

    $response->assertOk()->assertJsonPath('results.0.status', 'ok');

    expect(Order::query()->count())->toBe(1)
        ->and(Order::query()->first()?->sales_rep_id)->toBe($rep->id)
        ->and(SyncDevice::query()->count())->toBe(1)
        ->and(SyncAuditLog::query()->count())->toBe(1);
});

it('refuses an order for a customer not on the pushing reps route', function () {
    [$user] = repUser();
    actingAs($user);

    // Nobody's customer: no route at all, so it cannot be on this rep's.
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $response = postJson('/api/sync/push', pushPayload([
        orderEntity($customer->id, $product->id, 5000, null),
    ]));

    $response->assertOk()->assertJsonPath('results.0.status', 'error');
    expect(Order::query()->count())->toBe(0);
});

it('refuses a visit outcome for a customer not on the pushing reps route', function () {
    [$firstUser, $firstRep] = repUser();
    [$secondUser] = repUser();
    actingAs($secondUser);

    // On the FIRST rep's route, not the one pushing.
    $someoneElsesCustomer = customerFor($firstRep);

    $response = postJson('/api/sync/push', pushPayload([[
        'client_id' => (string) Str::ulid(),
        'entity_type' => 'visit_outcome',
        'data' => [
            'customer_id' => $someoneElsesCustomer->id,
            'outcome' => 'no_sale',
            'reason' => 'Shop closed',
            'occurred_at' => now()->toIso8601String(),
        ],
    ]]));

    $response->assertOk()->assertJsonPath('results.0.status', 'error');
    expect(VisitOutcome::query()->count())->toBe(0);
});

it('replays the same result when the same order is pushed twice', function () {
    [$user, $rep] = repUser();
    actingAs($user);

    $customer = customerFor($rep);
    $entity = orderEntity($customer->id, Product::factory()->create()->id, 5000, null);

    $first = postJson('/api/sync/push', pushPayload([$entity]));
    $second = postJson('/api/sync/push', pushPayload([$entity]));

    expect(Order::query()->count())->toBe(1)
        ->and($first->json('results.0.data.order_id'))->toBe($second->json('results.0.data.order_id'));
});

it('syncs two distinct offline orders from one reconnect exactly once each', function () {
    // The phase's own acceptance line: two orders offline, reconnect, both
    // sync exactly once. Docs/PLAN.md, Phase 3.
    [$user, $rep] = repUser();
    actingAs($user);

    $customerA = customerFor($rep);
    $customerB = customerFor($rep);
    $product = Product::factory()->create();

    $response = postJson('/api/sync/push', pushPayload([
        orderEntity($customerA->id, $product->id, 5000, null),
        orderEntity($customerB->id, $product->id, 7500, null),
    ]));

    $response->assertOk();

    expect($response->json('results.0.status'))->toBe('ok')
        ->and($response->json('results.1.status'))->toBe('ok')
        ->and(Order::query()->count())->toBe(2)
        ->and(Order::query()->pluck('reference')->unique())->toHaveCount(2)
        ->and(SyncAuditLog::query()->count())->toBe(2);
});

it('flags a conflict rather than overwriting when an id is reused for different content', function () {
    [$user, $rep] = repUser();
    actingAs($user);

    $customer = customerFor($rep);
    $product = Product::factory()->create();
    $clientId = (string) Str::ulid();

    postJson('/api/sync/push', pushPayload([
        orderEntity($customer->id, $product->id, 5000, null, $clientId),
    ]));

    $second = postJson('/api/sync/push', pushPayload([
        orderEntity($customer->id, $product->id, 6000, null, $clientId),
    ]));

    expect(Order::query()->count())->toBe(1)
        ->and($second->json('results.0.status'))->toBe('conflict')
        ->and(SyncConflict::query()->where('client_id', $clientId)->count())->toBe(1);
});

it('stores the rejected content on a conflict, not just its hash', function () {
    // A conflicts queue where the reviewer can't see what was rejected is
    // barely a queue — see the back-office resource this backs.
    [$user, $rep] = repUser();
    actingAs($user);

    $customer = customerFor($rep);
    $product = Product::factory()->create();
    $clientId = (string) Str::ulid();

    postJson('/api/sync/push', pushPayload([
        orderEntity($customer->id, $product->id, 5000, null, $clientId),
    ]));

    postJson('/api/sync/push', pushPayload([
        orderEntity($customer->id, $product->id, 6000, null, $clientId),
    ]));

    $conflict = SyncConflict::query()->where('client_id', $clientId)->sole();

    expect($conflict->rejected_payload)->not->toBeNull()
        ->and($conflict->rejected_payload['lines'][0]['unit_price_minor'])->toBe(6000);
});

it('refuses to replay a client_id back to a different rep than the one who submitted it', function () {
    [$firstUser, $firstRep] = repUser();
    [$secondUser] = repUser();

    $customer = customerFor($firstRep);
    $entity = orderEntity($customer->id, Product::factory()->create()->id, 5000, null);

    actingAs($firstUser);
    $first = postJson('/api/sync/push', pushPayload([$entity]));
    $first->assertJsonPath('results.0.status', 'ok');

    // A different rep's session somehow carries the exact same client_id
    // and payload — refused, not handed the first rep's order back.
    actingAs($secondUser);
    $second = postJson('/api/sync/push', pushPayload([$entity]));

    expect($second->json('results.0.status'))->toBe('error')
        ->and(Order::query()->count())->toBe(1);
});

it('submits a no-sale visit outcome', function () {
    [$user, $rep] = repUser();
    actingAs($user);

    $customer = customerFor($rep);

    $response = postJson('/api/sync/push', pushPayload([[
        'client_id' => (string) Str::ulid(),
        'entity_type' => 'visit_outcome',
        'data' => [
            'customer_id' => $customer->id,
            'route_id' => null,
            'outcome' => 'no_sale',
            'reason' => 'Shop closed',
            'occurred_at' => now()->toIso8601String(),
        ],
    ]]));

    $response->assertOk()->assertJsonPath('results.0.status', 'ok');

    expect(VisitOutcome::query()->count())->toBe(1)
        ->and(VisitOutcome::query()->first()?->reason)->toBe('Shop closed');
});

it('reports a no-sale with no reason as a per-entity error, not a crash', function () {
    [$user, $rep] = repUser();
    actingAs($user);

    $customer = customerFor($rep);

    $response = postJson('/api/sync/push', pushPayload([[
        'client_id' => (string) Str::ulid(),
        'entity_type' => 'visit_outcome',
        'data' => [
            'customer_id' => $customer->id,
            'outcome' => 'no_sale',
            'occurred_at' => now()->toIso8601String(),
        ],
    ]]));

    $response->assertOk()->assertJsonPath('results.0.status', 'error');
    expect(VisitOutcome::query()->count())->toBe(0);
});

it('flags a price variance and still keeps the rest of the batch working', function () {
    [$user, $rep] = repUser();
    actingAs($user);

    $customer = customerFor($rep);
    $list = PriceList::factory()->default()->create(['currency' => 'ETB']);
    $product = Product::factory()->create();
    PriceListItem::factory()->for($list, 'priceList')->pricedAt('50.00')->create(['product_id' => $product->id]);

    $otherCustomer = customerFor($rep);

    $response = postJson('/api/sync/push', pushPayload([
        // Captured at 45.00 against a list now priced at 50.00.
        orderEntity($customer->id, $product->id, 4500, $list->id),
        [
            'client_id' => (string) Str::ulid(),
            'entity_type' => 'visit_outcome',
            'data' => [
                'customer_id' => $otherCustomer->id,
                'outcome' => 'no_sale',
                'reason' => 'Owner away',
                'occurred_at' => now()->toIso8601String(),
            ],
        ],
    ]));

    $response->assertOk();

    expect($response->json('results.0.data.has_price_variance'))->toBeTrue()
        ->and($response->json('results.1.status'))->toBe('ok')
        ->and(Order::query()->first()?->has_price_variance)->toBeTrue();
});
