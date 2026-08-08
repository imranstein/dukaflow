<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\Routes\Pages;

use App\Modules\Distribution\Filament\Resources\Routes\RouteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoute extends CreateRecord
{
    protected static string $resource = RouteResource::class;
}
