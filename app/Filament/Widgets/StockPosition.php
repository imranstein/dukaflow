<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the stock actually is, summed from the ledger.
 *
 * Nothing stores a current quantity, so this is a rollup rather than a read.
 * See Docs/adr/0006-stock-ledger.md.
 */
class StockPosition extends TableWidget
{
    protected static ?string $heading = 'Stock position';

    protected int|string|array $columnSpan = 'full';

    // These are three cheap aggregates over a single distributor's data.
    // Lazy loading would cost a second round trip to save nothing, and
    // the dashboard renders in one pass without it.
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $directory = app(ScopeDirectory::class);
        $products = $directory->options(Scope::Product->value);
        $reps = $directory->options(Scope::SalesRep->value);
        $warehouses = Warehouse::query()->pluck('name', 'id')->all();

        return $table
            ->query($this->rollup())
            ->columns([
                TextColumn::make('product_id')
                    ->label('Product')
                    ->wrap()
                    ->formatStateUsing(fn (int $state): string => $products[$state] ?? "Product #{$state}"),

                TextColumn::make('location_id')
                    ->label('Where')
                    ->formatStateUsing(function (int $state, StockMovement $record) use ($reps, $warehouses): string {
                        return $record->location_type === LocationType::Van
                            ? ($reps[$state] ?? "Van #{$state}").' (van)'
                            : ($warehouses[$state] ?? "Warehouse #{$state}");
                    }),

                TextColumn::make('on_hand')
                    ->label('On hand')
                    ->alignEnd()
                    ->sortable()
                    // Negative means an adjustment took it below zero, which
                    // is worth seeing rather than hiding.
                    ->color(fn (?int $state): string => (int) $state < 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('on_hand', 'desc')
            ->emptyStateHeading('Nothing in stock yet');
    }

    /** @return Builder<StockMovement> */
    private function rollup(): Builder
    {
        return StockMovement::query()
            ->groupBy('product_id', 'location_type', 'location_id')
            ->select('product_id', 'location_type', 'location_id')
            ->selectRaw('min(id) as id')
            ->selectRaw('sum(quantity) as on_hand')
            ->havingRaw('sum(quantity) <> 0');
    }
}
