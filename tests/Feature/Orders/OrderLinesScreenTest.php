<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Orders\Filament\Resources\Orders\Pages\EditOrder;
use App\Modules\Orders\Filament\Resources\Orders\RelationManagers\LinesRelationManager;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderWriter;
use Filament\Actions\Testing\TestAction;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Without these there was no way to put a product on an order from the UI, so
 * the Submit button was offered on drafts that could never satisfy it. Every
 * action goes through OrderWriter: a plain relation form would write a line
 * with no price and no product details.
 */

beforeEach(function (): void {
    actingAs(User::factory()->admin()->create());
});

/** @return Testable<LinesRelationManager> */
function linesManager(Order $order): Testable
{
    return Livewire::test(LinesRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ]);
}

function orderable(string $price = '312.00'): Product
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

function draftOrder(): Order
{
    return app(OrderWriter::class)->startDraft(customerId: Customer::factory()->create()->id);
}

it('adds a line at the price from the list in force', function () {
    $order = draftOrder();
    $product = orderable('312.00');

    linesManager($order)
        ->callAction(TestAction::make('addLine')->table(), data: [
            'product_id' => $product->id,
            'quantity' => 4,
        ])
        ->assertHasNoActionErrors();

    $line = $order->refresh()->lines->sole();

    expect($line->unit_price_minor)->toBe(31200)
        ->and($line->line_total_minor)->toBe(124800)
        ->and($line->product_sku)->toBe($product->sku)
        ->and($order->total()->toDecimal())->toBe('1248.00');
});

it('refuses a product nothing prices, and says so', function () {
    $order = draftOrder();
    $unpriced = Product::factory()->create(['name' => 'Nobody prices this']);

    linesManager($order)
        ->callAction(TestAction::make('addLine')->table(), data: [
            'product_id' => $unpriced->id,
            'quantity' => 1,
        ])
        ->assertNotified();

    expect($order->refresh()->lines)->toBeEmpty();
});

it('keeps the total in step when a quantity changes on screen', function () {
    $order = draftOrder();
    $product = orderable('50.00');
    $line = app(OrderWriter::class)->addLine($order, $product->id, 2);

    linesManager($order)
        ->callAction(TestAction::make('changeQuantity')->table($line), data: ['quantity' => 10])
        ->assertHasNoActionErrors();

    expect($order->refresh()->total()->toDecimal())->toBe('500.00');
});

it('keeps the total in step when a line is removed on screen', function () {
    $order = draftOrder();
    $writer = app(OrderWriter::class);
    $writer->addLine($order, orderable('50.00')->id, 2);
    $doomed = $writer->addLine($order, orderable('25.00')->id, 4);

    linesManager($order)
        ->callAction(TestAction::make('delete')->table($doomed));

    expect($order->refresh()->lines)->toHaveCount(1)
        ->and($order->total()->toDecimal())->toBe('100.00');
});

it('hides the editing actions once the order leaves draft', function () {
    $order = draftOrder();
    app(OrderWriter::class)->addLine($order, orderable()->id, 1);
    $order->submit();

    linesManager($order->refresh())
        ->assertActionHidden(TestAction::make('addLine')->table());
});

it('shows the editing actions on a draft', function () {
    $order = draftOrder();
    app(OrderWriter::class)->addLine($order, orderable()->id, 1);

    linesManager($order->refresh())
        ->assertActionVisible(TestAction::make('addLine')->table());
});
