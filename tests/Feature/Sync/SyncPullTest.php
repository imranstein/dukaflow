<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\SalesRep;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/*
 * The pull side of Docs/adr/0002-offline-sync-strategy.md §4: a cursor a
 * device can hand back to get only what changed since.
 */

it('rejects an entity type nothing feeds', function () {
    $user = User::factory()->rep()->create();
    SalesRep::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    getJson('/api/sync/pull?device_id='.Str::ulid().'&entity_type=nonsense')
        ->assertStatus(422);
});

it('pulls everything on a null cursor and only what is new after', function () {
    $user = User::factory()->rep()->create();
    SalesRep::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Product::factory()->count(2)->create();
    $deviceId = (string) Str::ulid();

    $first = getJson('/api/sync/pull?device_id='.$deviceId.'&entity_type=product');
    $first->assertOk();

    expect($first->json('rows'))->toHaveCount(2)
        ->and($first->json('has_more'))->toBeFalse();

    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();

    // Nothing new yet: the same cursor pulls nothing.
    $empty = getJson('/api/sync/pull?device_id='.$deviceId.'&entity_type=product&cursor='.urlencode($cursor));
    expect($empty->json('rows'))->toBe([])
        // A cursor with nothing after it is handed back unchanged, not lost.
        ->and($empty->json('next_cursor'))->toBe($cursor);

    Product::factory()->create(['name' => 'New Arrival']);

    $after = getJson('/api/sync/pull?device_id='.$deviceId.'&entity_type=product&cursor='.urlencode($cursor));
    expect($after->json('rows'))->toHaveCount(1)
        ->and($after->json('rows.0.data.name'))->toBe('New Arrival');
});

it('respects the limit and says when there is more', function () {
    $user = User::factory()->rep()->create();
    SalesRep::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Product::factory()->count(5)->create();

    $response = getJson('/api/sync/pull?device_id='.Str::ulid().'&entity_type=product&limit=2');

    expect($response->json('rows'))->toHaveCount(2)
        ->and($response->json('has_more'))->toBeTrue();
});

it('only carries valid_ids for reconciliation on the last page of a pull', function () {
    // Docs/adr/0007-reconciling-stale-device-caches.md: reconciling
    // against a snapshot mid-pagination would prune ids the device hasn't
    // even finished catching up on yet.
    $user = User::factory()->rep()->create();
    SalesRep::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    $products = Product::factory()->count(3)->create();
    $deviceId = (string) Str::ulid();

    $partial = getJson('/api/sync/pull?device_id='.$deviceId.'&entity_type=product&limit=2');
    expect($partial->json('has_more'))->toBeTrue()
        ->and($partial->json('valid_ids'))->toBeNull();

    $cursor = $partial->json('next_cursor');
    $final = getJson('/api/sync/pull?device_id='.$deviceId.'&entity_type=product&limit=2&cursor='.urlencode($cursor));

    expect($final->json('has_more'))->toBeFalse()
        ->and($final->json('valid_ids'))->toEqualCanonicalizing($products->pluck('id')->all());
});
