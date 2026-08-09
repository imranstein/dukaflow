<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Orders\Pages\EditOrder;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Services\OrderWriter;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * The lifecycle rules live in the model, so the screen must not offer a way
 * round them. The generated form exposed status and total_minor as free
 * fields: a draft with no lines could be saved as fulfilled, skipping the
 * guards, the timestamps and the event that moves the stock.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

function sellableProduct(string $price = '100.00'): Product
{
    $product = Product::factory()->create();
    $list = PriceList::query()->where('is_default', true)->first()
        ?? PriceList::factory()->create(['is_default' => true, 'currency' => 'ETB']);

    PriceListItem::factory()->pricedAt($price)->create([
        'price_list_id' => $list->id,
        'product_id' => $product->id,
    ]);

    return $product;
}

it('does not offer a create form', function () {
    expect(OrderResource::canCreate())->toBeFalse()
        ->and(array_keys(OrderResource::getPages()))->toBe(['index', 'edit']);
});

it('will not accept a status typed into the form', function () {
    $order = Order::factory()->create();

    Livewire::test(EditOrder::class, ['record' => $order->getKey()])
        ->fillForm(['status' => OrderStatus::Fulfilled->value])
        ->call('save');

    // status is display-only, so nothing was written no matter what arrived.
    expect($order->fresh()?->status)->toBe(OrderStatus::Draft)
        ->and($order->fresh()?->fulfilled_at)->toBeNull();
});

it('will not accept a total typed into the form', function () {
    $order = Order::factory()->create(['total_minor' => 0]);

    Livewire::test(EditOrder::class, ['record' => $order->getKey()])
        ->fillForm(['total_minor' => 999999])
        ->call('save');

    expect($order->fresh()?->total_minor)->toBe(0);
});

it('opens an order through the writer, with a reference', function () {
    $outlet = Customer::factory()->create();

    Livewire::test(ListOrders::class)
        ->callAction('open', data: [
            'customer_id' => $outlet->id,
            'placed_at' => now()->toDateTimeString(),
        ])
        ->assertHasNoActionErrors();

    $order = Order::query()->sole();

    expect($order->reference)->toStartWith('SO-')
        ->and($order->status)->toBe(OrderStatus::Draft)
        ->and($order->customer_id)->toBe($outlet->id)
        ->and($order->currency)->toBe('ETB');
});

it('walks an order through its states with the buttons', function () {
    Warehouse::factory()->default()->create();
    $writer = app(OrderWriter::class);
    $order = $writer->startDraft(customerId: Customer::factory()->create()->id);
    $writer->addLine($order, sellableProduct()->id, 2);

    $page = Livewire::test(EditOrder::class, ['record' => $order->getKey()]);

    $page->callAction('submit');
    expect($order->fresh()?->status)->toBe(OrderStatus::Submitted);

    $page->callAction('approve');
    expect($order->fresh()?->status)->toBe(OrderStatus::Approved);
});

it('only offers the buttons the state allows', function () {
    $draft = Order::factory()->create();
    OrderLine::factory()->create(['order_id' => $draft->id]);

    Livewire::test(EditOrder::class, ['record' => $draft->getKey()])
        ->assertActionVisible('submit')
        ->assertActionHidden('approve')
        ->assertActionHidden('fulfil')
        ->assertActionVisible('cancelOrder');

    $done = Order::factory()->status(OrderStatus::Fulfilled)->create();

    Livewire::test(EditOrder::class, ['record' => $done->getKey()])
        ->assertActionHidden('submit')
        ->assertActionHidden('approve')
        ->assertActionHidden('fulfil')
        ->assertActionHidden('cancelOrder');
});

it('explains a fulfilment that fails instead of falling over', function () {
    Warehouse::factory()->default()->create();
    $writer = app(OrderWriter::class);
    $order = $writer->startDraft(customerId: Customer::factory()->create()->id, salesRepId: 4);
    $writer->addLine($order, sellableProduct()->id, 5);
    $order->submit()->approve();

    // Nothing on the van. The action reports it rather than throwing.
    Livewire::test(EditOrder::class, ['record' => $order->getKey()])
        ->callAction('fulfil')
        ->assertNotified();

    expect($order->fresh()?->status)->toBe(OrderStatus::Approved);
});

it('insists on a reason before cancelling', function () {
    $order = Order::factory()->create();
    OrderLine::factory()->create(['order_id' => $order->id]);

    Livewire::test(EditOrder::class, ['record' => $order->getKey()])
        ->callAction('cancelOrder', data: ['reason' => ''])
        ->assertHasActionErrors(['reason']);

    expect($order->fresh()?->status)->toBe(OrderStatus::Draft);
});

it('records the reason when one is given', function () {
    $order = Order::factory()->create();
    OrderLine::factory()->create(['order_id' => $order->id]);

    Livewire::test(EditOrder::class, ['record' => $order->getKey()])
        ->callAction('cancelOrder', data: ['reason' => 'Outlet has shut'])
        ->assertHasNoActionErrors();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()?->cancellation_reason)->toBe('Outlet has shut');
});
