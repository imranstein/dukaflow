<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Exceptions\OrderLineException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Support\Contracts\Pricebook;
use App\Support\Contracts\ProductCatalogue;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds orders.
 *
 * Everything that changes an order's lines goes through here, because three
 * things have to happen together and none of them is obvious from the outside:
 * the price has to come from the right price list, the product's details have
 * to be copied onto the line, and the order's total has to be rewritten.
 *
 * Pricing and product details both arrive through shared-kernel contracts, so
 * this module never names Catalog. See Docs/adr/0001-module-boundaries.md.
 */
final readonly class OrderWriter
{
    public function __construct(
        private Pricebook $pricebook,
        private ProductCatalogue $products,
    ) {}

    public function startDraft(
        int $customerId,
        ?int $salesRepId = null,
        ?int $routeId = null,
        ?Carbon $placedAt = null,
        string $currency = Money::DEFAULT_CURRENCY,
        ?string $clientId = null,
    ): Order {
        $placedAt ??= Carbon::now();

        return Order::query()->create([
            'reference' => $this->nextReference($placedAt),
            'client_id' => $clientId,
            'customer_id' => $customerId,
            'sales_rep_id' => $salesRepId,
            'route_id' => $routeId,

            // Normalised through Money, which is the only thing that decides
            // what a currency code looks like. Storing 'etb' here would make
            // every price on the order look like a currency mismatch.
            'currency' => Money::zero($currency)->currency,

            'placed_at' => $placedAt,
        ]);
    }

    /**
     * Adds a product to a draft, priced as at the day the order was taken.
     *
     * Adding a product already on the order increases its quantity rather
     * than creating a second line, which is what the person clicking twice
     * meant.
     */
    public function addLine(Order $order, int $productId, int $quantity): OrderLine
    {
        $order->assertLinesAreEditable();

        if ($quantity < 1) {
            throw OrderLineException::quantityMustBePositive($quantity);
        }

        $product = $this->products->describe($productId);

        if ($product === null) {
            throw OrderLineException::unknownProduct($productId);
        }

        if (! $product->isActive) {
            throw OrderLineException::productNotForSale($product->name);
        }

        $existing = $order->lines()->where('product_id', $productId)->first();

        if ($existing instanceof OrderLine) {
            return $this->changeQuantity($order, $existing, $existing->quantity + $quantity);
        }

        $price = $this->pricebook->priceFor(
            productId: $productId,
            customerId: $order->customer_id,
            routeId: $order->route_id,
            on: $order->placed_at,
        );

        if ($price === null) {
            throw OrderLineException::unpriced($product->name);
        }

        if ($price->currency !== $order->currency) {
            throw OrderLineException::wrongCurrency($product->name, $price->currency, $order->currency);
        }

        $priceListId = $this->pricebook->priceListIdFor(
            productId: $productId,
            customerId: $order->customer_id,
            routeId: $order->route_id,
            on: $order->placed_at,
        );

        return DB::transaction(function () use ($order, $product, $quantity, $price, $priceListId): OrderLine {
            $line = $order->lines()->create([
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'unit_code' => $product->unitCode,
                'quantity' => $quantity,
                'unit_price_minor' => $price->minorUnits,
                'line_total_minor' => OrderLine::totalFor($price->minorUnits, $quantity),
                'price_list_id' => $priceListId,
            ]);

            // The order carries the list its first line was priced under, so
            // Phase 3 has something to check an offline order against.
            if ($order->price_list_id === null && $priceListId !== null) {
                $order->price_list_id = $priceListId;
                $order->save();
            }

            $order->recalculateTotal();

            return $line;
        });
    }

    /**
     * Adds a line at a price fixed elsewhere — a sale captured offline,
     * priced against whatever the device's pricebook said when the rep rang
     * it up. Unlike addLine(), this never re-resolves the price: the rep
     * already quoted it and the customer already paid it, and repricing a
     * completed sale to today's number would make the order lie about what
     * happened. The caller compares this price against a fresh resolution
     * and flags the order if they disagree — see
     * Docs/adr/0002-offline-sync-strategy.md §5.
     *
     * Also unlike addLine(), a second line for a product already on the
     * order is refused rather than merged: an offline order arrives as one
     * whole document, and a capturing device that would send the same
     * product twice has a bug worth surfacing, not a quantity to add to.
     */
    public function addCapturedLine(
        Order $order,
        int $productId,
        int $quantity,
        Money $price,
        ?int $priceListId,
    ): OrderLine {
        $order->assertLinesAreEditable();

        if ($quantity < 1) {
            throw OrderLineException::quantityMustBePositive($quantity);
        }

        $product = $this->products->describe($productId);

        if ($product === null) {
            throw OrderLineException::unknownProduct($productId);
        }

        if ($price->currency !== $order->currency) {
            throw OrderLineException::wrongCurrency($product->name, $price->currency, $order->currency);
        }

        if ($order->lines()->where('product_id', $productId)->exists()) {
            throw OrderLineException::alreadyCaptured($product->name);
        }

        return DB::transaction(function () use ($order, $product, $quantity, $price, $priceListId): OrderLine {
            $line = $order->lines()->create([
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'unit_code' => $product->unitCode,
                'quantity' => $quantity,
                'unit_price_minor' => $price->minorUnits,
                'line_total_minor' => OrderLine::totalFor($price->minorUnits, $quantity),
                'price_list_id' => $priceListId,
            ]);

            if ($order->price_list_id === null && $priceListId !== null) {
                $order->price_list_id = $priceListId;
                $order->save();
            }

            $order->recalculateTotal();

            return $line;
        });
    }

    public function changeQuantity(Order $order, OrderLine $line, int $quantity): OrderLine
    {
        $this->assertLineBelongsTo($order, $line);
        $order->assertLinesAreEditable();

        if ($quantity < 1) {
            throw OrderLineException::quantityMustBePositive($quantity);
        }

        return DB::transaction(function () use ($order, $line, $quantity): OrderLine {
            $line->quantity = $quantity;
            $line->line_total_minor = OrderLine::totalFor($line->unit_price_minor, $quantity);
            $line->save();

            $order->recalculateTotal();

            return $line;
        });
    }

    public function removeLine(Order $order, OrderLine $line): void
    {
        $this->assertLineBelongsTo($order, $line);
        $order->assertLinesAreEditable();

        DB::transaction(function () use ($order, $line): void {
            $line->delete();
            $order->recalculateTotal();
        });
    }

    /**
     * Without this the editability guard protects the wrong record: pass a
     * fresh draft alongside a line from a frozen order and the guard sees a
     * draft, says yes, and the frozen order loses a line.
     */
    private function assertLineBelongsTo(Order $order, OrderLine $line): void
    {
        if ($line->order_id !== $order->id) {
            throw OrderLineException::lineBelongsToAnotherOrder($line->id, $order->reference);
        }
    }

    /**
     * References read SO-2026-00001. Sequential per year, which is what a
     * distributor's paperwork expects.
     *
     * The max-plus-one is taken inside the insert's transaction, so two
     * requests racing produce a unique-key violation rather than a duplicate
     * reference. At one distributor's volume that collision is theoretical;
     * a sequence table is the fix if it ever stops being.
     */
    private function nextReference(Carbon $placedAt): string
    {
        $year = $placedAt->format('Y');
        $prefix = "SO-{$year}-";

        // Ordered by length first, then value. Sorting these as plain strings
        // works only while the padding is uniform, and it stops being uniform
        // at the hundred-thousandth order of a year: 'SO-2026-99999' sorts
        // above 'SO-2026-100000', so the counter would hand out a number it
        // had already used and every later order would collide on the unique
        // key. Unlikely at one distributor's volume, and cheap to not have.
        $latest = Order::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByRaw('length(reference) desc')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $latest === null ? 1 : ((int) substr((string) $latest, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
