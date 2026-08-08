<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockMovements\Pages;

use App\Modules\Inventory\Enums\LocationType;
use App\Modules\Inventory\Filament\Resources\StockMovements\StockMovementResource;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedger;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

/**
 * The ledger is written by the domain, never by a form.
 *
 * A plain create form here would insert a row straight through the model and
 * skip StockLedger entirely — which is to say, skip the one rule this module
 * exists to enforce. So these are domain actions instead: each one names a
 * real thing that happens in a warehouse and goes through the service.
 */
class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->receiveAction(),
            $this->loadVanAction(),
            $this->returnFromVanAction(),
            $this->adjustAction(),
        ];
    }

    private function receiveAction(): Action
    {
        return Action::make('receive')
            ->label('Receive stock')
            ->icon('heroicon-o-arrow-down-tray')
            ->schema([
                $this->productField(),
                $this->warehouseField(),
                $this->quantityField()->helperText('How many selling units arrived.'),
                $this->dateField(),
            ])
            ->action(function (array $data): void {
                app(StockLedger::class)->receive(
                    productId: (int) $data['product_id'],
                    warehouseId: (int) $data['warehouse_id'],
                    quantity: (int) $data['quantity'],
                    on: Carbon::parse($data['occurred_at']),
                );

                Notification::make()->title('Stock received')->success()->send();
            });
    }

    private function loadVanAction(): Action
    {
        return Action::make('loadVan')
            ->label('Load a van')
            ->icon('heroicon-o-truck')
            ->schema([
                $this->productField(),
                $this->warehouseField()->label('From warehouse'),
                $this->repField()->label('Onto van'),
                $this->quantityField(),
                $this->dateField(),
            ])
            ->action(function (array $data): void {
                app(StockLedger::class)->loadVan(
                    productId: (int) $data['product_id'],
                    warehouseId: (int) $data['warehouse_id'],
                    salesRepId: (int) $data['sales_rep_id'],
                    quantity: (int) $data['quantity'],
                    on: Carbon::parse($data['occurred_at']),
                );

                Notification::make()->title('Van loaded')->success()->send();
            });
    }

    private function returnFromVanAction(): Action
    {
        return Action::make('returnFromVan')
            ->label('Return from a van')
            ->icon('heroicon-o-arrow-uturn-left')
            ->schema([
                $this->productField(),
                $this->repField()->label('From van'),
                $this->warehouseField()->label('Back into warehouse'),
                $this->quantityField(),
                $this->dateField(),
            ])
            ->action(function (array $data): void {
                app(StockLedger::class)->returnFromVan(
                    productId: (int) $data['product_id'],
                    salesRepId: (int) $data['sales_rep_id'],
                    warehouseId: (int) $data['warehouse_id'],
                    quantity: (int) $data['quantity'],
                    on: Carbon::parse($data['occurred_at']),
                );

                Notification::make()->title('Stock returned')->success()->send();
            });
    }

    private function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Record an adjustment')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->schema([
                $this->productField(),
                Select::make('location_type')
                    ->label('Where')
                    ->options(LocationType::options())
                    ->default(LocationType::Warehouse->value)
                    ->live()
                    ->required(),
                $this->warehouseField()
                    ->visible(fn ($get): bool => $get('location_type') === LocationType::Warehouse->value)
                    ->required(fn ($get): bool => $get('location_type') === LocationType::Warehouse->value),
                $this->repField()
                    ->visible(fn ($get): bool => $get('location_type') === LocationType::Van->value)
                    ->required(fn ($get): bool => $get('location_type') === LocationType::Van->value),
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->helperText('Negative to write stock off, positive to add it back.'),
                Textarea::make('reason')
                    ->required()
                    ->rows(2)
                    ->helperText('This is the whole point of an adjustment. Say what happened.'),
                $this->dateField(),
            ])
            ->action(function (array $data): void {
                $isVan = $data['location_type'] === LocationType::Van->value;

                app(StockLedger::class)->adjust(
                    productId: (int) $data['product_id'],
                    locationType: $isVan ? LocationType::Van : LocationType::Warehouse,
                    locationId: (int) ($isVan ? $data['sales_rep_id'] : $data['warehouse_id']),
                    quantity: (int) $data['quantity'],
                    reason: (string) $data['reason'],
                    on: Carbon::parse($data['occurred_at']),
                );

                Notification::make()->title('Adjustment recorded')->success()->send();
            });
    }

    private function productField(): Select
    {
        return Select::make('product_id')
            ->label('Product')
            ->options(fn (): array => app(ScopeDirectory::class)->options(Scope::Product->value))
            ->searchable()
            ->required();
    }

    private function warehouseField(): Select
    {
        return Select::make('warehouse_id')
            ->label('Warehouse')
            ->options(fn (): array => Warehouse::query()->active()->orderBy('name')->pluck('name', 'id')->all())
            ->default(fn (): ?int => Warehouse::query()->where('is_default', true)->value('id'))
            ->searchable()
            ->required();
    }

    private function repField(): Select
    {
        return Select::make('sales_rep_id')
            ->label('Sales rep')
            ->options(fn (): array => app(ScopeDirectory::class)->options(Scope::SalesRep->value))
            ->searchable()
            ->required();
    }

    private function quantityField(): TextInput
    {
        return TextInput::make('quantity')
            ->numeric()
            ->minValue(1)
            ->required();
    }

    private function dateField(): DatePicker
    {
        return DatePicker::make('occurred_at')
            ->label('When')
            ->default(today())
            ->required();
    }
}
