<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Support\Contracts\SyncFeed;
use App\Support\SyncCursor;

/**
 * Catalog's answer to a device's pull. Products only — pricing itself
 * travels pre-resolved per route rather than as raw lists a device would
 * have to run PriceResolver's logic against. See
 * Docs/adr/0002-offline-sync-strategy.md §5.
 */
final class CatalogSyncFeed implements SyncFeed
{
    /** @return list<string> */
    public function entityTypes(): array
    {
        return ['product'];
    }

    /**
     * $salesRepId is unused: the catalogue is shared distributor-wide, not
     * rep-specific, so nothing here needs scoping by who is asking.
     *
     * @return list<array{id: int, updated_at: string, data: array<string, mixed>}>
     */
    public function pull(string $entityType, ?SyncCursor $cursor, int $limit, ?int $salesRepId): array
    {
        if ($entityType !== 'product') {
            return [];
        }

        return SyncCursor::apply(Product::query()->with('unitOfMeasure'), $cursor)
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'updated_at' => $product->updated_at?->toIso8601String() ?? '',
                'data' => [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit' => $product->unitOfMeasure?->code,
                    'pack_size' => $product->pack_size,
                    'category' => $product->category,
                    'barcode' => $product->barcode,
                    // Deactivation is the deletion a device sees, per ADR-002
                    // §4 — a product never actually disappears from the feed.
                    'is_active' => $product->is_active,
                ],
            ])
            ->all();
    }
}
