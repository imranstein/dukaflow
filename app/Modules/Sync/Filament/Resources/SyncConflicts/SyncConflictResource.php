<?php

declare(strict_types=1);

namespace App\Modules\Sync\Filament\Resources\SyncConflicts;

use App\Modules\Sync\Filament\Resources\SyncConflicts\Pages\ListSyncConflicts;
use App\Modules\Sync\Filament\Resources\SyncConflicts\Pages\ViewSyncConflict;
use App\Modules\Sync\Filament\Resources\SyncConflicts\Schemas\SyncConflictInfolist;
use App\Modules\Sync\Filament\Resources\SyncConflicts\Tables\SyncConflictsTable;
use App\Modules\Sync\Models\SyncConflict;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A client_id resubmitted with different content — see
 * Docs/adr/0002-offline-sync-strategy.md §2. A conflict is produced by the
 * sync push handler, never by a human, so there is no create or edit form —
 * only a queue to review and mark resolved.
 */
class SyncConflictResource extends Resource
{
    protected static ?string $model = SyncConflict::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Sync';

    protected static ?string $navigationLabel = 'Conflicts';

    protected static ?string $recordTitleAttribute = 'client_id';

    public static function table(Table $table): Table
    {
        return SyncConflictsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SyncConflictInfolist::configure($schema);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncConflicts::route('/'),
            'view' => ViewSyncConflict::route('/{record}'),
        ];
    }
}
