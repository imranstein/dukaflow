<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Schemas;

use App\Modules\Catalog\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255)
                    ->unique(Product::class, ignoreRecord: true)
                    ->helperText('The code the warehouse and the reps both use.'),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Select::make('unit_of_measure_id')
                    ->label('Selling unit')
                    ->relationship('unitOfMeasure', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('What a rep orders one of: a carton, a crate, a sack.'),

                TextInput::make('pack_size')
                    ->label('Units per selling unit')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->helperText('24 bottles to a crate, 12 cartons to a case.'),

                TextInput::make('category')
                    ->maxLength(255)
                    ->datalist(fn (): array => Product::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category')
                        ->all()),

                TextInput::make('barcode')
                    ->maxLength(64)
                    ->unique(Product::class, ignoreRecord: true),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive products stay in past orders but cannot be ordered again.'),
            ]);
    }
}
