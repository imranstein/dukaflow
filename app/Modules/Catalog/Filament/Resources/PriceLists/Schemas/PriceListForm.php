<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\PriceLists\Schemas;

use App\Modules\Catalog\Models\PriceList;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PriceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(32)
                    ->unique(PriceList::class, ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                // Money only accepts a three letter code, and it throws on
                // anything else. Without this rule a typo here would be stored
                // happily and then blow up every screen that renders a price.
                TextInput::make('currency')
                    ->required()
                    ->default(Money::DEFAULT_CURRENCY)
                    ->length(3)
                    ->rule('regex:/^[A-Za-z]{3}$/')
                    ->validationMessages(['regex' => 'Use a three letter currency code, such as ETB or KES.'])
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state)))
                    ->helperText('ISO code. Every price on this list is in this currency.'),

                DatePicker::make('effective_from')
                    ->required()
                    ->default(today())
                    ->helperText('The first day this list applies.'),

                DatePicker::make('effective_to')
                    ->rule(fn (Get $get): string => 'after_or_equal:'.($get('effective_from') ?: '1900-01-01'))
                    ->validationMessages(['after_or_equal' => 'The list cannot stop applying before it starts.'])
                    ->helperText('Leave empty to keep it in force indefinitely.'),

                Toggle::make('is_default')
                    ->label('Default list')
                    ->helperText('Used for any customer with no list of their own.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
