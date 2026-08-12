<?php

declare(strict_types=1);

namespace App\Modules\Sync\Http\Controllers;

use App\Support\Contracts\RepDirectory;
use Illuminate\Http\Request;

/**
 * Every sync endpoint needs the same answer to "who is this," resolved from
 * the session rather than trusted from the request — see
 * Docs/adr/0002-offline-sync-strategy.md §8.
 */
trait ResolvesSyncRep
{
    private function resolveRep(Request $request, RepDirectory $reps): int
    {
        $userId = $request->user()?->id;
        $salesRepId = $userId === null ? null : $reps->repIdForUser($userId);

        if ($salesRepId === null) {
            abort(403, 'Only a sales rep can use sync.');
        }

        return $salesRepId;
    }
}
