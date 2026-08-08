<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Models\PriceListAssignment;
use App\Support\Events\ScopeRecordDeleted;

/**
 * Drops price list assignments that pointed at a record which no longer
 * exists. Without this the rows would linger, and an id reused by a later
 * record would silently inherit somebody else's pricing.
 */
final class PurgeAssignmentsForDeletedScope
{
    public function handle(ScopeRecordDeleted $event): void
    {
        PriceListAssignment::query()
            ->where('scope', $event->scope)
            ->where('scope_id', $event->id)
            ->delete();
    }
}
