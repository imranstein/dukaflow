<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Schemas;

use App\Modules\Orders\Enums\OrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->required(),
                TextInput::make('customer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('sales_rep_id')
                    ->numeric(),
                TextInput::make('route_id')
                    ->numeric(),
                TextInput::make('price_list_id')
                    ->numeric(),
                Select::make('status')
                    ->options(OrderStatus::class)
                    ->required(),
                TextInput::make('currency')
                    ->required(),
                TextInput::make('total_minor')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DateTimePicker::make('placed_at')
                    ->required(),
                DateTimePicker::make('submitted_at'),
                DateTimePicker::make('approved_at'),
                DateTimePicker::make('fulfilled_at'),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('cancellation_reason'),
            ]);
    }
}
