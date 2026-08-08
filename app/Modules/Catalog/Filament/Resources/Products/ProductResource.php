<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products;

use App\Modules\Catalog\Filament\Resources\Products\Pages\CreateProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Filament\Resources\Products\Schemas\ProductForm;
use App\Modules\Catalog\Filament\Resources\Products\Tables\ProductsTable;
use App\Modules\Catalog\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
