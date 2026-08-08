<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockReconciliations;

use App\Modules\Inventory\Filament\Resources\StockReconciliations\Pages\CreateStockReconciliation;
use App\Modules\Inventory\Filament\Resources\StockReconciliations\Pages\EditStockReconciliation;
use App\Modules\Inventory\Filament\Resources\StockReconciliations\Pages\ListStockReconciliations;
use App\Modules\Inventory\Filament\Resources\StockReconciliations\Schemas\StockReconciliationForm;
use App\Modules\Inventory\Filament\Resources\StockReconciliations\Tables\StockReconciliationsTable;
use App\Modules\Inventory\Models\StockReconciliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StockReconciliationResource extends Resource
{
    protected static ?string $model = StockReconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Reconciliations';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return StockReconciliationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockReconciliationsTable::configure($table);
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
            'index' => ListStockReconciliations::route('/'),
            'create' => CreateStockReconciliation::route('/create'),
            'edit' => EditStockReconciliation::route('/{record}/edit'),
        ];
    }
}
