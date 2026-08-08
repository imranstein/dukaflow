<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\Customers;

use App\Modules\Distribution\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Modules\Distribution\Filament\Resources\Customers\Pages\EditCustomer;
use App\Modules\Distribution\Filament\Resources\Customers\Pages\ListCustomers;
use App\Modules\Distribution\Filament\Resources\Customers\RelationManagers\VisitSchedulesRelationManager;
use App\Modules\Distribution\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Modules\Distribution\Filament\Resources\Customers\Tables\CustomersTable;
use App\Modules\Distribution\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Distribution';

    protected static ?string $navigationLabel = 'Outlets';

    // "Outlet" is what the trade calls the shop, and what the reps say. The
    // model stays Customer because that is what it is to the business.
    protected static ?string $modelLabel = 'outlet';

    protected static ?string $pluralModelLabel = 'outlets';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VisitSchedulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
