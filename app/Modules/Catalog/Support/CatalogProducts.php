<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Support\Contracts\ProductCatalogue;
use App\Support\ProductDescription;

final class CatalogProducts implements ProductCatalogue
{
    public function describe(int $productId): ?ProductDescription
    {
        $product = Product::query()->with('unitOfMeasure')->find($productId);

        return $product instanceof Product ? $this->toDescription($product) : null;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, ProductDescription>
     */
    public function describeMany(array $productIds): array
    {
        return Product::query()
            ->with('unitOfMeasure')
            ->whereIn('id', $productIds)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $this->toDescription($product),
            ])
            ->all();
    }

    private function toDescription(Product $product): ProductDescription
    {
        return new ProductDescription(
            id: $product->id,
            sku: $product->sku,
            name: $product->name,
            unitCode: $product->unitOfMeasure?->code,
            packSize: $product->pack_size,
            isActive: $product->is_active,
        );
    }
}
