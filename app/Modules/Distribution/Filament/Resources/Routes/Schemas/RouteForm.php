<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\Routes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('description'),
                Select::make('sales_rep_id')
                    ->relationship('salesRep', 'name'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
