<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Filament\Resources\Warehouses\Pages;

use App\Modules\Inventory\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;
}
