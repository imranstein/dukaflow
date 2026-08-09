<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders;

use App\Modules\Orders\Filament\Resources\Orders\Pages\EditOrder;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ListOrders;
use App\Modules\Orders\Filament\Resources\Orders\Schemas\OrderForm;
use App\Modules\Orders\Filament\Resources\Orders\Tables\OrdersTable;
use App\Modules\Orders\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    /**
     * Orders are opened by the action on the list page, which goes through
     * OrderWriter. A create form would write a row with no reference and no
     * currency normalisation.
     */
    public static function canCreate(): bool
    {
        return false;
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
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
