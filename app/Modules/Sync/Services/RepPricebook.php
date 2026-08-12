<?php

declare(strict_types=1);

namespace App\Modules\Sync\Services;

use App\Support\Contracts\Pricebook;
use App\Support\Contracts\SyncFeed;
use Illuminate\Support\Collection;

/**
 * A rep's whole route, pre-priced. The device stores this flat and looks a
 * price up rather than running PriceResolver's precedence rules itself — see
 * Docs/adr/0002-offline-sync-strategy.md §5.
 */
final readonly class RepPricebook
{
    // ponytail: a single page from each feed, not real pagination. Correct
    // at the scale this application is for — one distributor, a route of
    // under twenty outlets. Paginate through the feed properly if a
    // distributor ever has more products or customers than this covers.
    private const int FEED_PAGE = 1000;

    public function __construct(
        private SyncFeed $feed,
        private Pricebook $pricebook,
    ) {}

    /** @return list<array{customer_id: int, product_id: int, unit_price_minor: int, currency: string, price_list_id: int|null}> */
    public function forRep(int $salesRepId): array
    {
        $routeIds = $this->routeIdsFor($salesRepId);

        if ($routeIds === []) {
            return [];
        }

        $customerRoutes = $this->activeCustomerRoutes($routeIds, $salesRepId);
        $productIds = $this->activeProductIds();

        $prices = [];

        foreach ($customerRoutes as $customerId => $routeId) {
            foreach ($productIds as $productId) {
                $price = $this->pricebook->priceFor($productId, customerId: $customerId, routeId: $routeId);

                if ($price === null) {
                    continue;
                }

                $prices[] = [
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                    'unit_price_minor' => $price->minorUnits,
                    'currency' => $price->currency,
                    'price_list_id' => $this->pricebook->priceListIdFor($productId, customerId: $customerId, routeId: $routeId),
                ];
            }
        }

        return $prices;
    }

    /** @return list<int> */
    private function routeIdsFor(int $salesRepId): array
    {
        return $this->rows('route', $salesRepId)
            ->filter(fn (array $row): bool => ($row['data']['sales_rep_id'] ?? null) === $salesRepId
                && ($row['data']['is_active'] ?? false))
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $routeIds
     * @return Collection<int, int> customer id => route id
     */
    private function activeCustomerRoutes(array $routeIds, int $salesRepId): Collection
    {
        return $this->rows('customer', $salesRepId)
            ->filter(fn (array $row): bool => in_array($row['data']['route_id'] ?? null, $routeIds, true)
                && ($row['data']['is_active'] ?? false))
            ->mapWithKeys(fn (array $row): array => [$row['id'] => $row['data']['route_id']]);
    }

    /** @return list<int> */
    private function activeProductIds(): array
    {
        return $this->rows('product', null)
            ->filter(fn (array $row): bool => $row['data']['is_active'] ?? false)
            ->pluck('id')
            ->all();
    }

    /**
     * The feed is already scoped by $salesRepId (Docs/adr/0002-offline-sync-strategy.md
     * §4/§7); the filtering above stays anyway as a second, cheap check —
     * one place getting the scoping wrong should not be the only thing
     * standing between a rep and another rep's customer list.
     *
     * @return Collection<int, array{id: int, updated_at: string, data: array<string, mixed>}>
     */
    private function rows(string $entityType, ?int $salesRepId): Collection
    {
        return collect($this->feed->pull($entityType, null, self::FEED_PAGE, $salesRepId));
    }
}
