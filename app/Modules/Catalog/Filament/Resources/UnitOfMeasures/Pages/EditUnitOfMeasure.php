<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\UnitOfMeasures\Pages;

use App\Modules\Catalog\Filament\Resources\UnitOfMeasures\UnitOfMeasureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUnitOfMeasure extends EditRecord
{
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
