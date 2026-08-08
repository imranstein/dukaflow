<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\Customers\Schemas;

use App\Modules\Distribution\Enums\OutletType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('outlet_type')
                    ->options(OutletType::class)
                    ->required(),
                TextInput::make('owner_name'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('address'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                Select::make('route_id')
                    ->relationship('route', 'name'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
