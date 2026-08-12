<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Contracts\SyncFeed;

/**
 * The one feed Sync asks, made of the feeds each module contributes. Same
 * shape as CompositeScopeDirectory: a module registers what it can answer
 * for, and the composite finds the one that does.
 */
final class CompositeSyncFeed implements SyncFeed
{
    /** @var list<SyncFeed> */
    private array $feeds = [];

    public function register(SyncFeed $feed): void
    {
        $this->feeds[] = $feed;
    }

    /** @return list<string> */
    public function entityTypes(): array
    {
        $types = [];

        foreach ($this->feeds as $feed) {
            array_push($types, ...$feed->entityTypes());
        }

        return $types;
    }

    public function handles(string $entityType): bool
    {
        return in_array($entityType, $this->entityTypes(), strict: true);
    }

    /** @return list<array{id: int, updated_at: string, data: array<string, mixed>}> */
    public function pull(string $entityType, ?SyncCursor $cursor, int $limit, ?int $salesRepId): array
    {
        return $this->feedFor($entityType)?->pull($entityType, $cursor, $limit, $salesRepId) ?? [];
    }

    private function feedFor(string $entityType): ?SyncFeed
    {
        foreach ($this->feeds as $feed) {
            if (in_array($entityType, $feed->entityTypes(), strict: true)) {
                return $feed;
            }
        }

        return null;
    }
}
