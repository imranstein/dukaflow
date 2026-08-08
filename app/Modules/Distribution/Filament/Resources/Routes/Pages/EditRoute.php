<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\Routes\Pages;

use App\Modules\Distribution\Filament\Resources\Routes\RouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoute extends EditRecord
{
    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
