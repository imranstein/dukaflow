<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\SalesReps\Pages;

use App\Modules\Distribution\Filament\Resources\SalesReps\SalesRepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesRep extends EditRecord
{
    protected static string $resource = SalesRepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
