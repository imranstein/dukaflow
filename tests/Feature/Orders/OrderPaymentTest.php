<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderPayment;

/*
 * A payment here is a note that money arrived, by cash or on credit. There is
 * no gateway and there will not be one; the brief makes that a hard boundary.
 */

function orderWorth(string $total): Order
{
    $order = Order::factory()->create(['currency' => 'ETB']);
    OrderLine::factory()->of(productId: 1, quantity: 1, unitPrice: $total)->create(['order_id' => $order->id]);

    return $order->recalculateTotal()->refresh();
}

it('owes its total before anything is paid', function () {
    $order = orderWorth('1248.00');

    expect($order->total()->toDecimal())->toBe('1248.00')
        ->and($order->amountPaid()->isZero())->toBeTrue()
        ->and($order->balance()->toDecimal())->toBe('1248.00')
        ->and($order->isSettled())->toBeFalse();
});

it('counts a payment against the balance', function () {
    $order = orderWorth('1000.00');

    OrderPayment::factory()->of('400.00')->create(['order_id' => $order->id]);

    expect($order->amountPaid()->toDecimal())->toBe('400.00')
        ->and($order->balance()->toDecimal())->toBe('600.00')
        ->and($order->isSettled())->toBeFalse();
});

it('settles when the payments add up', function () {
    $order = orderWorth('1000.00');

    OrderPayment::factory()->of('400.00')->create(['order_id' => $order->id]);
    OrderPayment::factory()->of('600.00')->create(['order_id' => $order->id]);

    expect($order->balance()->isZero())->toBeTrue()
        ->and($order->isSettled())->toBeTrue();
});

it('does not report a negative balance when someone overpays', function () {
    $order = orderWorth('100.00');

    OrderPayment::factory()->of('150.00')->create(['order_id' => $order->id]);

    expect($order->balance()->toDecimal())->toBe('0.00')
        ->and($order->balance()->isNegative())->toBeFalse()
        ->and($order->amountPaid()->toDecimal())->toBe('150.00');
});

it('records credit as a payment without pretending cash arrived', function () {
    $order = orderWorth('500.00');
    $credit = OrderPayment::factory()->of('500.00', PaymentMethod::Credit)->create(['order_id' => $order->id]);

    expect($credit->method)->toBe(PaymentMethod::Credit)
        ->and($credit->method->settlesImmediately())->toBeFalse()
        ->and(PaymentMethod::Cash->settlesImmediately())->toBeTrue()
        ->and($order->balance()->isZero())->toBeTrue();
});

it('keeps the payment in the order currency', function () {
    $order = orderWorth('120.00');
    $payment = OrderPayment::factory()->of('120.00')->create(['order_id' => $order->id]);

    expect($payment->amount()->currency)->toBe($order->currency)
        ->and($payment->amount()->format())->toBe('ETB 120.00');
});

it('offers exactly two ways to pay', function () {
    expect(PaymentMethod::options())->toBe([
        'cash' => 'Cash',
        'credit' => 'On credit',
    ]);
});

it('loses the payments with the order', function () {
    $order = orderWorth('100.00');
    OrderPayment::factory()->of('100.00')->create(['order_id' => $order->id]);

    $order->delete();

    expect(OrderPayment::query()->count())->toBe(0);
});
