<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Schemas;

use App\Modules\Orders\Models\Order;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Money;
use App\Support\Scope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Only what a person may safely change.
 *
 * The generated version of this form exposed status and total_minor as free
 * fields, which meant a draft with no lines could be saved as fulfilled: no
 * transition guard, no timestamp, no event, and so no stock movement either.
 * Status moves through the actions on the edit page; the total is written by
 * OrderWriter from the lines. Both are shown here, and neither can be typed.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $directory = app(ScopeDirectory::class);

        return $schema
            ->components([
                TextInput::make('reference')
                    ->label('Order number')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Issued in sequence when the order is opened.'),

                TextInput::make('status')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (?Order $record): string => $record?->status->label() ?? 'Draft')
                    ->helperText('Changed with the buttons above, so the rules are applied.'),

                Select::make('customer_id')
                    ->label('Outlet')
                    ->options(fn (): array => $directory->options(Scope::Customer->value))
                    ->searchable()
                    ->required()
                    ->disabled(fn (?Order $record): bool => $record !== null && ! $record->status->allowsEditingLines()),

                Select::make('sales_rep_id')
                    ->label('Rep')
                    ->options(fn (): array => $directory->options(Scope::SalesRep->value))
                    ->searchable()
                    ->placeholder('Taken in the office'),

                Select::make('route_id')
                    ->label('Route')
                    ->options(fn (): array => $directory->options(Scope::Route->value))
                    ->searchable()
                    ->placeholder('None'),

                DateTimePicker::make('placed_at')
                    ->label('Taken at')
                    ->required()
                    ->disabled(fn (?Order $record): bool => $record !== null && ! $record->status->allowsEditingLines())
                    ->helperText('When the rep took it, which prices the order.'),

                TextInput::make('total')
                    ->label('Total')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(
                        fn (?Order $record): string => $record?->total()->format() ?? Money::zero()->format(),
                    )
                    ->helperText('Added up from the lines below.'),

                TextInput::make('owed')
                    ->label('Outstanding')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(
                        fn (?Order $record): string => $record?->balance()->format() ?? Money::zero()->format(),
                    ),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),

                TextInput::make('cancellation_reason')
                    ->label('Cancelled because')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (?Order $record): bool => $record?->cancellation_reason !== null),
            ]);
    }
}
