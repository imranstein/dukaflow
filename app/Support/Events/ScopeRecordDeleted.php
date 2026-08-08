<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Support\Contracts\ScopeDirectory;

/**
 * A record that other modules may be pointing at has been deleted.
 *
 * Modules reference each other's records by bare id, without a foreign key,
 * because the database cannot enforce a reference across a boundary the code
 * is not allowed to cross. This event is what replaces `on delete cascade`:
 * the owning module announces the deletion in primitives, and whoever kept a
 * reference cleans up after themselves.
 *
 * @see ScopeDirectory for the read side of the same idea
 */
final readonly class ScopeRecordDeleted
{
    public function __construct(
        public string $scope,
        public int $id,
    ) {}
}
