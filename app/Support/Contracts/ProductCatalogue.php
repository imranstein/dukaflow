<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use App\Support\ProductDescription;

/**
 * Reads product details across a module boundary.
 *
 * Orders needs a product's sku, name and unit to copy onto a line, and is not
 * allowed to load a Product. Catalog answers in values instead.
 */
interface ProductCatalogue
{
    public function describe(int $productId): ?ProductDescription;

    /**
     * @param  list<int>  $productIds
     * @return array<int, ProductDescription> keyed by product id
     */
    public function describeMany(array $productIds): array;
}
