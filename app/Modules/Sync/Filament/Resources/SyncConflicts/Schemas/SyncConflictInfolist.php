<?php

declare(strict_types=1);

namespace App\Modules\Sync\Filament\Resources\SyncConflicts\Schemas;

use App\Modules\Sync\Models\SyncAuditLog;
use App\Modules\Sync\Models\SyncConflict;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SyncConflictInfolist
{
    /**
     * A plain <pre>, not Filament's CodeEntry — that needs phiki, which
     * isn't in this project's dependency list, and json_encode is all a
     * read-only diff of two payloads needs.
     *
     * Pre-rendered to a string via ->state() rather than ->formatStateUsing():
     * TextEntry treats array state as a list of items to render one by one
     * (Arr::wrap + foreach), which is right for tags but wrong for a single
     * JSON blob — it hands formatStateUsing each leaf scalar instead of the
     * whole payload.
     */
    /** @param  array<string, mixed>|null  $payload */
    private static function json(?array $payload): string
    {
        return $payload === null || $payload === []
            ? '(none)'
            : '<pre style="white-space: pre-wrap">'.e(json_encode($payload, JSON_PRETTY_PRINT)).'</pre>';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('occurred_at')->label('When')->dateTime(),
            TextEntry::make('device.sales_rep_id')
                ->label('Rep')
                ->formatStateUsing(fn (?int $state): string => $state === null
                    ? 'Unknown'
                    : (app(ScopeDirectory::class)->label(Scope::SalesRep->value, $state) ?? "#{$state}")),
            TextEntry::make('entity_type')->label('Kind')->badge(),
            TextEntry::make('client_id')->label('Client id')->fontFamily('mono')->copyable(),

            Section::make('What the device sent')
                ->description('Rejected — same client_id as a row that already synced, with different content.')
                ->schema([
                    TextEntry::make('rejected_payload')
                        ->label(null)
                        ->html()
                        ->columnSpanFull()
                        ->state(fn (SyncConflict $record): string => self::json($record->rejected_payload)),
                ]),

            Section::make('What actually synced')
                ->description('The row this client_id already belongs to.')
                ->schema([
                    TextEntry::make('winning_payload')
                        ->label(null)
                        ->html()
                        ->columnSpanFull()
                        // Not a column — resolved by (client_id, entity_type), the
                        // same lookup SyncPushHandler uses for idempotency.
                        ->state(fn (SyncConflict $record): string => self::json(
                            SyncAuditLog::forClientId($record->client_id, $record->entity_type)?->response_payload
                        )),
                ]),
        ]);
    }
}
