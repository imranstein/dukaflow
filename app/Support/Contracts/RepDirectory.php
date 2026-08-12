<?php

declare(strict_types=1);

namespace App\Support\Contracts;

/**
 * Which sales rep an authenticated user is, if any. Sync's HTTP layer uses
 * this to resolve who is pushing from the session rather than trusting
 * anything the request body claims to be. See
 * Docs/adr/0002-offline-sync-strategy.md §8.
 */
interface RepDirectory
{
    public function repIdForUser(int $userId): ?int;
}
