<?php

declare(strict_types=1);

namespace App\Modules\Sync\Filament\Resources\SyncConflicts\Tables;

use App\Modules\Sync\Models\SyncConflict;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SyncConflictsTable
{
    public static function configure(Table $table): Table
    {
        // The rep lives on the device, not on the conflict row itself — see
        // Docs/adr/0002-offline-sync-strategy.md §2. Resolved once per page
        // rather than once per row, same as OrdersTable.
        $reps = app(ScopeDirectory::class)->options(Scope::SalesRep->value);

        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('device.sales_rep_id')
                    ->label('Rep')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'Unknown' : ($reps[$state] ?? "#{$state}")),

                TextColumn::make('entity_type')
                    ->label('Kind')
                    ->badge(),

                TextColumn::make('client_id')
                    ->label('Client id')
                    ->fontFamily('mono')
                    ->copyable()
                    ->limit(12),

                IconColumn::make('resolved')
                    ->label('Resolved')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('resolved'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resolve')
                    ->label('Mark resolved')
                    ->icon('heroicon-o-check')
                    ->visible(fn (SyncConflict $record): bool => ! $record->resolved)
                    ->action(fn (SyncConflict $record) => $record->update(['resolved' => true])),
            ]);
    }
}
