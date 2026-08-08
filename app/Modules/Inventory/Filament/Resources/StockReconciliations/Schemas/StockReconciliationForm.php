<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockReconciliations\Schemas;

use App\Modules\Inventory\Enums\ReconciliationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockReconciliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sales_rep_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('reconciled_on')
                    ->required(),
                Select::make('status')
                    ->options(ReconciliationStatus::class)
                    ->required(),
                DateTimePicker::make('closed_at'),
                TextInput::make('notes'),
            ]);
    }
}
