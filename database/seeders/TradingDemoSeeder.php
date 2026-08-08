<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Inventory\Models\StockReconciliationLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Services\OrderWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A few days of trading, so the order and stock screens have something on
 * them and the demo shows the whole loop rather than empty tables.
 *
 * Like DatabaseSeeder, this sits above the modules: stocking a warehouse with
 * the catalogue and loading it onto a named rep is exactly the cross-module
 * knowledge the modules themselves are not allowed to hold.
 */
class TradingDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Trading data is history. Re-running the seeder should not stack a
        // second week of it on top of the first.
        if (Order::query()->exists() || StockMovement::query()->exists()) {
            return;
        }

        $ledger = app(StockLedger::class);
        $writer = app(OrderWriter::class);

        $depot = Warehouse::query()->where('is_default', true)->first();
        $products = Product::query()->where('is_active', true)->get();

        if ($depot === null || $products->isEmpty()) {
            return;
        }

        // Stock arrives.
        foreach ($products as $product) {
            $ledger->receive($product->id, $depot->id, 500, Carbon::today()->subDays(6));
        }

        $routes = Route::query()->with('salesRep')->whereNotNull('sales_rep_id')->get();

        foreach ($routes as $index => $route) {
            $rep = $route->salesRep;

            if ($rep === null) {
                continue;
            }

            $day = Carbon::today()->subDays(5 - min($index, 4));
            $carrying = $products->take(8);

            foreach ($carrying as $product) {
                $ledger->loadVan($product->id, $depot->id, $rep->id, 30, $day);
            }

            $outlets = Customer::query()->where('route_id', $route->id)->take(3)->get();

            foreach ($outlets as $position => $outlet) {
                $order = $writer->startDraft(
                    customerId: $outlet->id,
                    salesRepId: $rep->id,
                    routeId: $route->id,
                    placedAt: $day->copy()->addHours(9 + $position),
                );

                foreach ($carrying->take(3 + $position) as $product) {
                    $writer->addLine($order, $product->id, 2 + $position);
                }

                // A spread of states, so the board is not all one colour.
                match ($position) {
                    0 => $this->completeAndPay($order),
                    1 => $order->submit()->approve(),
                    default => $order->submit(),
                };
            }

            // The first rep's day gets counted, one case short.
            if ($index === 0) {
                $this->countTheVan($rep->id, $carrying->take(4), $ledger, $day);
            }
        }
    }

    private function completeAndPay(Order $order): void
    {
        $order->submit()->approve()->fulfil();

        OrderPayment::query()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Cash,
            'amount_minor' => $order->refresh()->total_minor,
            'received_on' => $order->placed_at->toDateString(),
            'reference' => 'Receipt '.$order->reference,
        ]);
    }

    /** @param  Collection<int, Product>  $products */
    private function countTheVan(
        int $salesRepId,
        Collection $products,
        StockLedger $ledger,
        Carbon $day,
    ): void {
        $reconciliation = StockReconciliation::query()->create([
            'sales_rep_id' => $salesRepId,
            'reconciled_on' => $day,
            'notes' => 'End of round count.',
        ]);

        foreach ($products as $index => $product) {
            $expected = $ledger->balance($product->id, LocationType::Van, $salesRepId);

            StockReconciliationLine::query()->create([
                'stock_reconciliation_id' => $reconciliation->id,
                'product_id' => $product->id,
                'expected_quantity' => $expected,

                // One case short on the first product: a bottle broke.
                'counted_quantity' => $index === 0 ? max(0, $expected - 1) : $expected,
            ]);
        }
    }
}
