<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\Routes;

use App\Modules\Distribution\Filament\Resources\Routes\Pages\CreateRoute;
use App\Modules\Distribution\Filament\Resources\Routes\Pages\EditRoute;
use App\Modules\Distribution\Filament\Resources\Routes\Pages\ListRoutes;
use App\Modules\Distribution\Filament\Resources\Routes\Schemas\RouteForm;
use App\Modules\Distribution\Filament\Resources\Routes\Tables\RoutesTable;
use App\Modules\Distribution\Models\Route;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RouteResource extends Resource
{
    protected static ?string $model = Route::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?string $navigationLabel = 'Routes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoutesTable::configure($table);
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
            'index' => ListRoutes::route('/'),
            'create' => CreateRoute::route('/create'),
            'edit' => EditRoute::route('/{record}/edit'),
        ];
    }
}
