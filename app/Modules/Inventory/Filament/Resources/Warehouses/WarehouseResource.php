<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\Warehouses;

use App\Modules\Inventory\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Modules\Inventory\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Modules\Inventory\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Modules\Inventory\Filament\Resources\Warehouses\Schemas\WarehouseForm;
use App\Modules\Inventory\Filament\Resources\Warehouses\Tables\WarehousesTable;
use App\Modules\Inventory\Models\Warehouse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Warehouses';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
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
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
