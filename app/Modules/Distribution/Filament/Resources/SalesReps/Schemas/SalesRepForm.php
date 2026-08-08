<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\SalesReps\Schemas;

use App\Modules\Distribution\Models\SalesRep;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SalesRepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Login')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->unique(SalesRep::class, ignoreRecord: true)
                    ->validationMessages(['unique' => 'That login already belongs to another rep.'])
                    ->helperText('Optional. Reps who only use the field app do not need one.'),
                TextInput::make('code')
                    ->required()
                    ->maxLength(32)
                    ->unique(SalesRep::class, ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),
                TextInput::make('name')
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
