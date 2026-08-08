<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockMovements\Schemas;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Enums\MovementType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product_id')
                    ->required()
                    ->numeric(),
                Select::make('location_type')
                    ->options(LocationType::class)
                    ->required(),
                TextInput::make('location_id')
                    ->required()
                    ->numeric(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                Select::make('type')
                    ->options(MovementType::class)
                    ->required(),
                TextInput::make('reference_type'),
                TextInput::make('reference_id')
                    ->numeric(),
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('notes'),
            ]);
    }
}
