<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockReconciliations\Pages;

use App\Modules\Inventory\Filament\Resources\StockReconciliations\StockReconciliationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStockReconciliation extends EditRecord
{
    protected static string $resource = StockReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
