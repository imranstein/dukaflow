<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;

/**
 * Lets other modules name a product or a price list without depending on
 * Catalog. Orders is the first caller: an order line records a product id,
 * and the screens have to show what that id means.
 */
final class CatalogDirectory implements ScopeDirectory
{
    public function handles(string $scope): bool
    {
        return in_array($scope, [
            Scope::Product->value,
            Scope::PriceList->value,
        ], strict: true);
    }

    /** @return array<int, string> */
    public function options(string $scope): array
    {
        /** @var array<int, string> $options */
        $options = match ($scope) {
            Scope::Product->value => Product::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'sku', 'name'])
                ->mapWithKeys(fn (Product $product): array => [
                    $product->id => "{$product->sku} — {$product->name}",
                ])
                ->all(),
            Scope::PriceList->value => PriceList::query()->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };

        return $options;
    }

    public function label(string $scope, int $id): ?string
    {
        $name = match ($scope) {
            Scope::Product->value => Product::query()->whereKey($id)->value('name'),
            Scope::PriceList->value => PriceList::query()->whereKey($id)->value('name'),
            default => null,
        };

        return is_string($name) ? $name : null;
    }
}
