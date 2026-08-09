<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * Where an order is in its life, and where it may go next.
 *
 * The transition table lives here rather than in the model so that a sync
 * endpoint, a Filament action and a console command all apply the same rules.
 * See Docs/adr/0005-order-lifecycle.md.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Fulfilled => 'Fulfilled',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::Cancelled],
            self::Approved => [self::Fulfilled, self::Cancelled],

            // The goods have gone, or the order was abandoned. Undoing either
            // is a return, which is a different transaction entirely.
            self::Fulfilled, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), strict: true);
    }

    /** Nothing more happens to an order in this state. */
    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** Lines may only be touched while the order is still being built. */
    public function allowsEditingLines(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Whether the goods have actually left, which is the only question the
     * ledger can answer for. There was a hasCommittedStock() here that also
     * counted Approved; nothing called it, and it would have told a caller
     * that approving an order moves stock, which it does not.
     */
    public function hasMovedStock(): bool
    {
        return $this === self::Fulfilled;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
