<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\UnitOfMeasures\Pages;

use App\Modules\Catalog\Filament\Resources\UnitOfMeasures\UnitOfMeasureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUnitOfMeasure extends CreateRecord
{
    protected static string $resource = UnitOfMeasureResource::class;
}
