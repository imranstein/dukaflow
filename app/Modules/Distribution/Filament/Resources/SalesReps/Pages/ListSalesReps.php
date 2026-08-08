<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\SalesReps\Pages;

use App\Modules\Distribution\Filament\Resources\SalesReps\SalesRepResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesReps extends ListRecords
{
    protected static string $resource = SalesRepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
