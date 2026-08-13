<?php

declare(strict_types=1);

namespace App\Modules\Sync\Filament\Resources\SyncConflicts\Pages;

use App\Modules\Sync\Filament\Resources\SyncConflicts\SyncConflictResource;
use Filament\Resources\Pages\ListRecords;

class ListSyncConflicts extends ListRecords
{
    protected static string $resource = SyncConflictResource::class;
}
