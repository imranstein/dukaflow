<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\PriceLists\Pages;

use App\Modules\Catalog\Filament\Resources\PriceLists\PriceListResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePriceList extends CreateRecord
{
    protected static string $resource = PriceListResource::class;
}
