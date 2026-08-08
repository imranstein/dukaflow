<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderTransitionException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Support\Events\OrderFulfilled;
use Illuminate\Support\Facades\Event;

/*
 * The transition rules from Docs/adr/0005-order-lifecycle.md. These are what
 * a sync endpoint will lean on in Phase 3, when orders start arriving hours
 * late and out of order, so they are tested against the model rather than
 * through a form.
 */

function orderWithLines(OrderStatus $status = OrderStatus::Draft, int $lines = 1): Order
{
    $order = Order::factory()->status($status)->create();
    OrderLine::factory()->count($lines)->create(['order_id' => $order->id]);

    return $order->refresh();
}

it('starts as a draft', function () {
    expect(Order::factory()->create()->status)->toBe(OrderStatus::Draft);
});

it('walks the happy path', function () {
    Event::fake([OrderFulfilled::class]);
    $order = orderWithLines();

    $order->submit();
    expect($order->status)->toBe(OrderStatus::Submitted)
        ->and($order->submitted_at)->not->toBeNull();

    $order->approve();
    expect($order->status)->toBe(OrderStatus::Approved)
        ->and($order->approved_at)->not->toBeNull();

    $order->fulfil();
    expect($order->status)->toBe(OrderStatus::Fulfilled)
        ->and($order->fulfilled_at)->not->toBeNull()
        ->and($order->fresh()?->status)->toBe(OrderStatus::Fulfilled);
});

it('refuses to submit an order with nothing on it', function () {
    Order::factory()->create()->submit();
})->throws(OrderTransitionException::class, 'An order with no lines cannot be submitted.');

it('refuses every transition the state does not allow', function (OrderStatus $from, string $method) {
    $order = orderWithLines($from);

    $order->{$method}();
})->with([
    'approve a draft' => [OrderStatus::Draft, 'approve'],
    'fulfil a draft' => [OrderStatus::Draft, 'fulfil'],
    'fulfil a submitted order' => [OrderStatus::Submitted, 'fulfil'],
    'submit an approved order' => [OrderStatus::Approved, 'submit'],
    'submit a fulfilled order' => [OrderStatus::Fulfilled, 'submit'],
    'approve a fulfilled order' => [OrderStatus::Fulfilled, 'approve'],
    'cancel a fulfilled order' => [OrderStatus::Fulfilled, 'cancel'],
    'submit a cancelled order' => [OrderStatus::Cancelled, 'submit'],
    'approve a cancelled order' => [OrderStatus::Cancelled, 'approve'],
    'cancel a cancelled order' => [OrderStatus::Cancelled, 'cancel'],
])->throws(OrderTransitionException::class);

it('can be cancelled from any state before the goods leave', function (OrderStatus $from) {
    $order = orderWithLines($from);

    $order->cancel('Outlet closed');

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancellation_reason)->toBe('Outlet closed')
        ->and($order->cancelled_at)->not->toBeNull();
})->with([
    'draft' => [OrderStatus::Draft],
    'submitted' => [OrderStatus::Submitted],
    'approved' => [OrderStatus::Approved],
]);

it('says why a transition was refused', function () {
    $order = orderWithLines();

    expect(fn () => $order->approve())
        ->toThrow(OrderTransitionException::class, 'A Draft order cannot become Approved. It can only become: Submitted, Cancelled.');
});

it('says when an order is past changing', function () {
    $order = orderWithLines(OrderStatus::Fulfilled);

    expect(fn () => $order->cancel())
        ->toThrow(OrderTransitionException::class, 'A Fulfilled order is final and cannot become Cancelled.');
});

it('freezes the lines once the order leaves draft', function (OrderStatus $status) {
    orderWithLines($status)->assertLinesAreEditable();
})->with([
    'submitted' => [OrderStatus::Submitted],
    'approved' => [OrderStatus::Approved],
    'fulfilled' => [OrderStatus::Fulfilled],
    'cancelled' => [OrderStatus::Cancelled],
])->throws(OrderTransitionException::class, 'Only a draft can be edited.');

it('lets a draft be edited', function () {
    orderWithLines()->assertLinesAreEditable();
})->throwsNoExceptions();

it('announces a fulfilment so stock can follow', function () {
    Event::fake([OrderFulfilled::class]);

    $order = Order::factory()->status(OrderStatus::Approved)->create(['sales_rep_id' => 7]);
    OrderLine::factory()->create(['order_id' => $order->id, 'product_id' => 42, 'quantity' => 3]);
    OrderLine::factory()->create(['order_id' => $order->id, 'product_id' => 99, 'quantity' => 5]);

    $order->refresh()->fulfil();

    Event::assertDispatched(OrderFulfilled::class, function (OrderFulfilled $event) use ($order): bool {
        return $event->orderId === $order->id
            && $event->salesRepId === 7
            && $event->quantities === [42 => 3, 99 => 5];
    });
});

it('says nothing when an order is merely approved', function () {
    Event::fake([OrderFulfilled::class]);

    orderWithLines(OrderStatus::Submitted)->approve();

    Event::assertNotDispatched(OrderFulfilled::class);
});

it('knows which states have committed stock', function () {
    expect(OrderStatus::Draft->hasCommittedStock())->toBeFalse()
        ->and(OrderStatus::Submitted->hasCommittedStock())->toBeFalse()
        ->and(OrderStatus::Approved->hasCommittedStock())->toBeTrue()
        ->and(OrderStatus::Fulfilled->hasCommittedStock())->toBeTrue()
        ->and(OrderStatus::Cancelled->hasCommittedStock())->toBeFalse();
});

it('knows which states are the end of the road', function () {
    expect(OrderStatus::Fulfilled->isFinal())->toBeTrue()
        ->and(OrderStatus::Cancelled->isFinal())->toBeTrue()
        ->and(OrderStatus::Draft->isFinal())->toBeFalse()
        ->and(OrderStatus::Approved->isFinal())->toBeFalse();
});
