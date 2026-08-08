<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\SalesReps;

use App\Modules\Distribution\Filament\Resources\SalesReps\Pages\CreateSalesRep;
use App\Modules\Distribution\Filament\Resources\SalesReps\Pages\EditSalesRep;
use App\Modules\Distribution\Filament\Resources\SalesReps\Pages\ListSalesReps;
use App\Modules\Distribution\Filament\Resources\SalesReps\Schemas\SalesRepForm;
use App\Modules\Distribution\Filament\Resources\SalesReps\Tables\SalesRepsTable;
use App\Modules\Distribution\Models\SalesRep;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalesRepResource extends Resource
{
    protected static ?string $model = SalesRep::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?string $navigationLabel = 'Sales reps';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SalesRepForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesRepsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReps::route('/'),
            'create' => CreateSalesRep::route('/create'),
            'edit' => EditSalesRep::route('/{record}/edit'),
        ];
    }
}
