<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockMovements;

use App\Modules\Inventory\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Modules\Inventory\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Modules\Inventory\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The ledger, read-only.
 *
 * There is deliberately no create or edit page. A form here would write a row
 * through the model and skip StockLedger, and with it the rule that a balance
 * may not go negative outside an adjustment. Movements are recorded by the
 * domain actions on the list page instead.
 */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock ledger';

    protected static ?string $modelLabel = 'movement';

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
