<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\PriceLists;

use App\Modules\Catalog\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Modules\Catalog\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Modules\Catalog\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Modules\Catalog\Filament\Resources\PriceLists\RelationManagers\ItemsRelationManager;
use App\Modules\Catalog\Filament\Resources\PriceLists\Schemas\PriceListForm;
use App\Modules\Catalog\Filament\Resources\PriceLists\Tables\PriceListsTable;
use App\Modules\Catalog\Models\PriceList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Price lists';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PriceListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
