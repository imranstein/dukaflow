<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\UnitOfMeasures\Schemas;

use App\Modules\Catalog\Models\UnitOfMeasure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnitOfMeasureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(32)
                    ->unique(UnitOfMeasure::class, ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),
                TextInput::make('name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
