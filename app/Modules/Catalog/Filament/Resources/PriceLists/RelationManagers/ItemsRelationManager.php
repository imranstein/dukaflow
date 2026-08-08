<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\PriceLists\RelationManagers;

use App\Modules\Catalog\Models\PriceList;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The prices on a list.
 *
 * Everything here converts between the decimal a person types and the minor
 * units the column stores. See Docs/adr/0004-money-handling.md.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Prices';

    protected static ?string $modelLabel = 'price';

    // These tables hold a handful of rows each, so the extra round trip that
    // lazy loading costs buys nothing, and the panel renders in one pass.
    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->label('Product')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('price_list_id', $this->getOwnerRecord()->getKey()))
                ->validationMessages(['unique' => 'This product already has a price on this list.']),

            TextInput::make('unit_price_minor')
                ->label('Unit price')
                ->required()
                ->prefix($this->currency())
                ->rule('regex:/^\d+(\.\d{1,2})?$/')
                ->validationMessages(['regex' => 'Enter an amount with at most two decimal places.'])
                ->helperText('Price for one selling unit, the carton or crate the product is ordered in.')
                ->formatStateUsing(
                    fn (?int $state): ?string => $state === null ? null : Money::ofMinor($state)->toDecimal(),
                )
                ->dehydrateStateUsing(
                    fn (string $state): int => Money::fromDecimal($state)->minorUnits,
                ),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            // Every row shows the product and its unit, so load them with the
            // prices rather than two more queries per row.
            ->modifyQueryUsing(fn ($query) => $query->with('product.unitOfMeasure'))
            ->columns([
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.unitOfMeasure.code')
                    ->label('Unit')
                    ->badge(),
                TextColumn::make('unit_price_minor')
                    ->label('Unit price')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(
                        fn (int $state): string => Money::ofMinor($state, $this->currency())->format(),
                    ),
            ])
            ->defaultSort('id')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    private function currency(): string
    {
        $priceList = $this->getOwnerRecord();

        return $priceList instanceof PriceList ? $priceList->currency : Money::DEFAULT_CURRENCY;
    }
}
