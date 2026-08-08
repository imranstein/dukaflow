<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Tables;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Money;
use App\Support\Scope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        // Outlets, reps and routes live in other modules, so their names come
        // from the directory rather than a join. Resolved once for the page
        // instead of once per row.
        $directory = app(ScopeDirectory::class);
        $outlets = $directory->options(Scope::Customer->value);
        $reps = $directory->options(Scope::SalesRep->value);
        $routes = $directory->options(Scope::Route->value);

        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('customer_id')
                    ->label('Outlet')
                    ->formatStateUsing(fn (int $state): string => $outlets[$state] ?? "Deleted outlet #{$state}")
                    ->wrap(),

                TextColumn::make('sales_rep_id')
                    ->label('Rep')
                    ->placeholder('Office')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : ($reps[$state] ?? "#{$state}")),

                TextColumn::make('route_id')
                    ->label('Route')
                    ->placeholder('—')
                    ->toggleable()
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : ($routes[$state] ?? "#{$state}")),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Draft => 'gray',
                        OrderStatus::Submitted => 'info',
                        OrderStatus::Approved => 'warning',
                        OrderStatus::Fulfilled => 'success',
                        OrderStatus::Cancelled => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('total_minor')
                    ->label('Total')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(
                        fn (int $state, Order $record): string => Money::ofMinor($state, $record->currency)->format(),
                    ),

                TextColumn::make('balance')
                    ->label('Owed')
                    ->alignEnd()
                    ->state(fn (Order $record): string => $record->balance()->format())
                    ->color(fn (Order $record): string => $record->isSettled() ? 'success' : 'danger'),

                TextColumn::make('placed_at')
                    ->label('Taken')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::options()),
                SelectFilter::make('sales_rep_id')->label('Rep')->options($reps),
                SelectFilter::make('route_id')->label('Route')->options($routes),
                Filter::make('unpaid')
                    ->label('Not settled')
                    ->query(fn (Builder $query): Builder => $query->whereRaw(
                        'total_minor > (select coalesce(sum(amount_minor), 0) from order_payments where order_payments.order_id = orders.id)'
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
