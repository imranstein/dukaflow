<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Pages;

use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
