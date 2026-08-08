<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockMovements\Tables;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        // Products belong to Catalog and vans are reps, who belong to
        // Distribution. Both are named through the directory, resolved once
        // for the page rather than once per row.
        $directory = app(ScopeDirectory::class);
        $products = $directory->options(Scope::Product->value);
        $reps = $directory->options(Scope::SalesRep->value);

        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime('d M Y')
                    ->sortable(),

                TextColumn::make('product_id')
                    ->label('Product')
                    ->wrap()
                    ->formatStateUsing(fn (int $state): string => $products[$state] ?? "Deleted product #{$state}"),

                TextColumn::make('location_id')
                    ->label('Where')
                    ->formatStateUsing(function (int $state, StockMovement $record) use ($reps): string {
                        return $record->location_type === LocationType::Van
                            ? ($reps[$state] ?? "Van #{$state}").' (van)'
                            : "Warehouse #{$state}";
                    }),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (MovementType $state): string => $state->label())
                    ->color(fn (MovementType $state): string => match ($state) {
                        MovementType::Receipt => 'success',
                        MovementType::VanLoad => 'info',
                        MovementType::VanReturn => 'gray',
                        MovementType::Sale => 'primary',
                        MovementType::Adjustment => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('quantity')
                    ->alignEnd()
                    ->sortable()
                    // Signed on purpose: the direction is the information.
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('reference_id')
                    ->label('Against')
                    ->placeholder('—')
                    ->formatStateUsing(
                        fn (?int $state, StockMovement $record): ?string => $state === null
                            ? null
                            : ucfirst((string) $record->reference_type)." #{$state}",
                    )
                    ->toggleable(),

                TextColumn::make('notes')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')->options(MovementType::options()),
                SelectFilter::make('location_type')->label('Place')->options(LocationType::options()),
                SelectFilter::make('location_id')->label('Van')->options($reps),
            ])
            // No row or bulk actions: the ledger is append-only, so there is
            // nothing here to edit or delete.
            ->recordActions([])
            ->toolbarActions([]);
    }
}
