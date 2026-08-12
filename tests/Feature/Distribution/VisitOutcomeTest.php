<?php

declare(strict_types=1);

use App\Modules\Distribution\Enums\VisitOutcomeType;
use App\Modules\Distribution\Exceptions\VisitOutcomeException;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitOutcome;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/*
 * A visit outcome is written once, by the device that captured it, per
 * Docs/adr/0002-offline-sync-strategy.md §3. What the model itself has to
 * protect is narrow: a no-sale is worthless without a reason.
 */

it('records an order placed outcome', function () {
    $customer = Customer::factory()->create();
    $rep = SalesRep::factory()->create();

    $outcome = VisitOutcome::record([
        'client_id' => (string) Str::ulid(),
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'outcome' => VisitOutcomeType::OrderPlaced,
        'order_id' => 1,
        'order_reference' => 'SO-2026-00001',
        'occurred_at' => now(),
    ]);

    expect($outcome->outcome)->toBe(VisitOutcomeType::OrderPlaced)
        ->and($outcome->order_reference)->toBe('SO-2026-00001');
});

it('refuses a no-sale with nothing explaining it', function () {
    $customer = Customer::factory()->create();
    $rep = SalesRep::factory()->create();

    VisitOutcome::record([
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'outcome' => VisitOutcomeType::NoSale,
        'occurred_at' => now(),
    ]);
})->throws(VisitOutcomeException::class, 'A no-sale outcome needs a reason.');

it('accepts a no-sale with a reason', function () {
    $outcome = VisitOutcome::factory()->noSale('Owner away')->create();

    expect($outcome->outcome)->toBe(VisitOutcomeType::NoSale)
        ->and($outcome->reason)->toBe('Owner away');
});

it('keeps client ids unique when both are present', function () {
    $id = (string) Str::ulid();

    VisitOutcome::factory()->create(['client_id' => $id]);

    VisitOutcome::factory()->create(['client_id' => $id]);
})->throws(QueryException::class);
