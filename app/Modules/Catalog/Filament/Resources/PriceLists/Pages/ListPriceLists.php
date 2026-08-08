<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\PriceLists\Pages;

use App\Modules\Catalog\Filament\Resources\PriceLists\PriceListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceLists extends ListRecords
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
