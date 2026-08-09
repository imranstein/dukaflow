<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockReconciliations\Schemas;

use App\Modules\Inventory\Models\StockReconciliation;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Only what a person may set.
 *
 * The generated form offered status and closed_at as editable fields. Neither
 * is fillable, so changing them looked like it worked and did nothing — the
 * worst kind of control. A reconciliation is closed by the action on the edit
 * page, which is what writes the adjustments.
 */
class StockReconciliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sales_rep_id')
                    ->label('Rep')
                    ->options(fn (): array => app(ScopeDirectory::class)->options(Scope::SalesRep->value))
                    ->searchable()
                    ->required()
                    ->disabled(fn (?StockReconciliation $record): bool => $record !== null && ! $record->isOpen()),

                DatePicker::make('reconciled_on')
                    ->label('Counted on')
                    ->default(today())
                    ->required()
                    ->disabled(fn (?StockReconciliation $record): bool => $record !== null && ! $record->isOpen()),

                TextInput::make('status')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (?StockReconciliation $record): string => $record?->status->label() ?? 'Open')
                    ->helperText('Closing writes the adjustments, so it happens through the button.'),

                TextInput::make('notes')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
