<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\StockMovements\Pages;

use App\Modules\Inventory\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;
}
