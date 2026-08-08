<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\SalesReps\Pages;

use App\Modules\Distribution\Filament\Resources\SalesReps\SalesRepResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesRep extends CreateRecord
{
    protected static string $resource = SalesRepResource::class;
}
