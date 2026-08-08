<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockReconciliations\Pages;

use App\Modules\Inventory\Filament\Resources\StockReconciliations\StockReconciliationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockReconciliation extends CreateRecord
{
    protected static string $resource = StockReconciliationResource::class;
}
