<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockReconciliations\Pages;

use App\Modules\Inventory\Filament\Resources\StockReconciliations\StockReconciliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockReconciliations extends ListRecords
{
    protected static string $resource = StockReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
