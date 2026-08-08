<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * What a customer pays for a product on a given day.
 *
 * Orders has to price its lines, and pricing belongs to Catalog. Rather than
 * Orders importing Catalog's resolver, both meet at this contract: Catalog
 * implements it, Orders depends on it, and the two modules stay ignorant of
 * each other. Everything crossing the boundary is a primitive or a shared
 * kernel type.
 */
interface Pricebook
{
    /**
     * The price, or null when no list in force prices this product for this
     * customer.
     */
    public function priceFor(
        int $productId,
        ?int $customerId = null,
        ?int $routeId = null,
        ?Carbon $on = null,
    ): ?Money;

    /**
     * Which price list supplied that price.
     *
     * An order records this so it stays readable after the list is superseded,
     * and so Phase 3 can tell whether an order captured offline was priced
     * under a list that has since changed.
     */
    public function priceListIdFor(
        int $productId,
        ?int $customerId = null,
        ?int $routeId = null,
        ?Carbon $on = null,
    ): ?int;
}
