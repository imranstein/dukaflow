<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockMovements\Pages;

use App\Modules\Inventory\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
