<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A delta pull's position: everything after this timestamp, plus, at that
 * exact timestamp, everything with a higher id. The tuple is what survives
 * two rows updated in the same second — a bare timestamp cursor drops one of
 * them depending on which side of > it rounds to.
 *
 * See Docs/adr/0002-offline-sync-strategy.md §4.
 */
final readonly class SyncCursor
{
    public function __construct(
        public Carbon $updatedAt,
        public int $id,
    ) {}

    /** The token a device hands back on its next pull. Opaque on purpose. */
    public static function decode(?string $token): ?self
    {
        if ($token === null || $token === '') {
            return null;
        }

        $decoded = base64_decode($token, strict: true);

        if ($decoded === false || ! str_contains($decoded, '|')) {
            throw new InvalidArgumentException("Not a cursor: [{$token}].");
        }

        [$timestamp, $id] = explode('|', $decoded, 2);

        return new self(Carbon::parse($timestamp), (int) $id);
    }

    public function encode(): string
    {
        return base64_encode($this->updatedAt->toIso8601String().'|'.$this->id);
    }

    /**
     * Applies "after this cursor" and the ordering it depends on. Every
     * SyncFeed uses this rather than writing the tuple comparison out by
     * hand each time.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?self $cursor): Builder
    {
        if ($cursor !== null) {
            $query->where(function (Builder $query) use ($cursor): void {
                $query->where('updated_at', '>', $cursor->updatedAt)
                    ->orWhere(function (Builder $query) use ($cursor): void {
                        $query->where('updated_at', $cursor->updatedAt)
                            ->where('id', '>', $cursor->id);
                    });
            });
        }

        return $query->orderBy('updated_at')->orderBy('id');
    }
}
