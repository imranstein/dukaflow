<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Money;
use App\Support\Scope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * What each beat has been worth over the past week.
 */
class OrdersByRoute extends TableWidget
{
    protected static ?string $heading = 'Orders by route, last 7 days';

    protected int|string|array $columnSpan = 'full';

    // These are three cheap aggregates over a single distributor's data.
    // Lazy loading would cost a second round trip to save nothing, and
    // the dashboard renders in one pass without it.
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $routes = app(ScopeDirectory::class)->options(Scope::Route->value);

        return $table
            ->query($this->rollup())
            ->columns([
                TextColumn::make('route_id')
                    ->label('Route')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? 'No route'
                        : ($routes[$state] ?? "Route #{$state}")),

                TextColumn::make('orders')->label('Orders')->alignEnd()->sortable(),

                TextColumn::make('outlets')->label('Outlets served')->alignEnd(),

                TextColumn::make('value_minor')
                    ->label('Value')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => Money::ofMinor((int) $state)->format()),
            ])
            ->defaultSort('value_minor', 'desc')
            ->paginated(false)
            ->emptyStateHeading('No orders in the last week');
    }

    /** @return Builder<Order> */
    private function rollup(): Builder
    {
        return Order::query()
            // Cancelled orders were never business; drafts were never asked for.
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Draft])
            ->where('placed_at', '>=', Carbon::today()->subDays(7))
            ->groupBy('route_id')
            ->select('route_id')
            ->selectRaw('min(id) as id')
            ->selectRaw('count(*) as orders')
            ->selectRaw('count(distinct customer_id) as outlets')
            ->selectRaw('sum(total_minor) as value_minor');
    }
}
