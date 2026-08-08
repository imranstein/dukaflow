<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Enums\PriceListScope;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out what a customer pays for a product on a given day.
 *
 * This is the public face of Catalog's pricing. Other modules ask it questions
 * using plain identifiers and get a Money back; they never touch price list
 * models directly. See Docs/adr/0001-module-boundaries.md.
 *
 * Several price lists can apply to the same customer at once — one attached to
 * them specifically, one to their route, and the house default. The narrowest
 * assignment wins. Where two lists sit at the same level, the one that came
 * into force most recently wins, so re-pricing is a matter of opening a newer
 * list rather than editing the old one. Two lists that also start on the same
 * day are settled by taking the one created later.
 */
final class PriceResolver
{
    /** Default lists are considered only after customer and route assignments. */
    private const int DEFAULT_PRECEDENCE = 30;

    public function priceFor(
        int $productId,
        ?int $customerId = null,
        ?int $routeId = null,
        ?Carbon $on = null,
    ): ?Money {
        $date = ($on ?? Carbon::today())->copy()->startOfDay();

        foreach ($this->candidatesInPrecedenceOrder($customerId, $routeId, $date) as $priceList) {
            $item = $priceList->items->firstWhere('product_id', $productId);

            if ($item !== null) {
                return Money::ofMinor($item->unit_price_minor, $priceList->currency);
            }
        }

        return null;
    }

    /**
     * The price list that would be used for a product, or null when nothing
     * prices it. Useful for showing a rep which list they are ordering under.
     */
    public function priceListFor(
        int $productId,
        ?int $customerId = null,
        ?int $routeId = null,
        ?Carbon $on = null,
    ): ?PriceList {
        $date = ($on ?? Carbon::today())->copy()->startOfDay();

        foreach ($this->candidatesInPrecedenceOrder($customerId, $routeId, $date) as $priceList) {
            if ($priceList->items->firstWhere('product_id', $productId) !== null) {
                return $priceList;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, PriceList>
     */
    private function candidatesInPrecedenceOrder(?int $customerId, ?int $routeId, Carbon $date): Collection
    {
        /** @var list<array{precedence: int, list: PriceList}> $ranked */
        $ranked = [];

        if ($customerId !== null) {
            foreach ($this->assignedLists(PriceListScope::Customer, $customerId, $date) as $list) {
                $ranked[] = ['precedence' => PriceListScope::Customer->precedence(), 'list' => $list];
            }
        }

        if ($routeId !== null) {
            foreach ($this->assignedLists(PriceListScope::Route, $routeId, $date) as $list) {
                $ranked[] = ['precedence' => PriceListScope::Route->precedence(), 'list' => $list];
            }
        }

        foreach ($this->defaultLists($date) as $list) {
            $ranked[] = ['precedence' => self::DEFAULT_PRECEDENCE, 'list' => $list];
        }

        // Narrowest scope first, then whichever came into force most recently.
        // The id breaks a same-day tie: without it the comparator returns 0,
        // and the winner would be decided by whatever order the rows happened
        // to come back in — which is to say, not decided at all.
        usort($ranked, function (array $a, array $b): int {
            return [$a['precedence'], $b['list']->effective_from->getTimestamp(), $b['list']->id]
                <=> [$b['precedence'], $a['list']->effective_from->getTimestamp(), $a['list']->id];
        });

        return collect($ranked)
            ->map(fn (array $entry): PriceList => $entry['list'])
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, PriceList>
     */
    private function assignedLists(PriceListScope $scope, int $scopeId, Carbon $date): Collection
    {
        $priceListIds = PriceListAssignment::query()
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->pluck('price_list_id');

        if ($priceListIds->isEmpty()) {
            return collect();
        }

        return PriceList::query()
            ->whereIn('id', $priceListIds)
            ->effectiveOn($date)
            ->with('items')
            ->get();
    }

    /**
     * @return Collection<int, PriceList>
     */
    private function defaultLists(Carbon $date): Collection
    {
        return PriceList::query()
            ->where('is_default', true)
            ->effectiveOn($date)
            ->with('items')
            ->get();
    }
}
