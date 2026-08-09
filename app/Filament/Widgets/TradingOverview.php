<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\ReconciliationStatus;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReconciliation;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The four numbers a distributor looks at first thing.
 *
 * Widgets live above the modules on purpose: this one reads orders and stock
 * together, which is exactly the cross-module knowledge the modules are not
 * allowed to hold. Same reasoning as the demo seeders.
 */
class TradingOverview extends StatsOverviewWidget
{
    // These are three cheap aggregates over a single distributor's data.
    // Lazy loading would cost a second round trip to save nothing, and
    // the dashboard renders in one pass without it.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            $this->ordersToday(),
            $this->owed(),
            $this->stockOnVans(),
            $this->openCounts(),
        ];
    }

    private function ordersToday(): Stat
    {
        $today = Order::query()->placedOn(Carbon::today())->count();
        $value = (int) Order::query()->placedOn(Carbon::today())->sum('total_minor');

        return Stat::make('Orders today', (string) $today)
            ->description(Money::ofMinor($value)->format().' taken')
            ->descriptionIcon('heroicon-m-clipboard-document-list')
            ->color($today > 0 ? 'success' : 'gray');
    }

    private function owed(): Stat
    {
        // Summed per order and floored at zero, matching Order::balance().
        // Netting the totals against the payments in one go would let an
        // overpaid order cancel out somebody else's debt, so the dashboard
        // and the orders table would disagree about the same money.
        $balance = 'total_minor - coalesce((select sum(amount_minor) from order_payments '
            .'where order_payments.order_id = orders.id), 0)';

        $owed = (int) Order::query()
            // Cancelled orders are not owed, and drafts were never asked for.
            ->whereIn('status', [OrderStatus::Submitted, OrderStatus::Approved, OrderStatus::Fulfilled])
            ->selectRaw(
                "coalesce(sum(case when {$balance} > 0 then {$balance} else 0 end), 0) as owed"
            )
            ->value('owed');

        return Stat::make('Outstanding', Money::ofMinor($owed)->format())
            ->description('Invoiced and not yet settled')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color($owed > 0 ? 'warning' : 'success');
    }

    private function stockOnVans(): Stat
    {
        $onVans = (int) StockMovement::query()
            ->where('location_type', LocationType::Van)
            ->sum('quantity');

        // Counted in SQL rather than by pulling the groups back and counting
        // them, since a grouped count() would return one row per van.
        $vansCarrying = DB::query()->fromSub(
            StockMovement::query()
                ->where('location_type', LocationType::Van)
                ->groupBy('location_id')
                ->havingRaw('sum(quantity) > 0')
                ->select('location_id'),
            'carrying',
        )->count();

        return Stat::make('Units on vans', (string) $onVans)
            ->description($vansCarrying.' '.str($vansCarrying === 1 ? 'van' : 'vans')->toString().' carrying stock')
            ->descriptionIcon('heroicon-m-truck')
            ->color('info');
    }

    private function openCounts(): Stat
    {
        $open = StockReconciliation::query()->where('status', ReconciliationStatus::Open)->count();

        $withVariance = StockReconciliation::query()
            ->where('status', ReconciliationStatus::Open)
            ->whereHas('lines', fn ($query) => $query->whereColumn('counted_quantity', '!=', 'expected_quantity'))
            ->count();

        return Stat::make('Counts to settle', (string) $open)
            ->description($withVariance > 0 ? "{$withVariance} with a variance" : 'Nothing outstanding')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color($withVariance > 0 ? 'danger' : 'success');
    }
}
