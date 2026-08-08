<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Tables;

use App\Modules\Catalog\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // The unit is shown on every row, so load it with the products
            // rather than once per row.
            ->modifyQueryUsing(fn ($query) => $query->with('unitOfMeasure'))
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable()
                    ->placeholder('Uncategorised'),
                TextColumn::make('unitOfMeasure.code')
                    ->label('Unit')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('pack_size')
                    ->label('Per unit')
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sku')
            ->filters([
                SelectFilter::make('category')
                    ->options(fn (): array => Product::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
                SelectFilter::make('unit_of_measure_id')
                    ->label('Unit')
                    ->relationship('unitOfMeasure', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
