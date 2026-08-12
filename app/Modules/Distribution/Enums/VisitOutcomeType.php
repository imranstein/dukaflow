<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Enums;

/**
 * What happened when a rep actually stood in front of an outlet, as opposed
 * to what the schedule expected. See Docs/adr/0002-offline-sync-strategy.md.
 */
enum VisitOutcomeType: string
{
    case OrderPlaced = 'order_placed';
    case NoSale = 'no_sale';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => 'Order placed',
            self::NoSale => 'No sale',
        };
    }

    /** A no-sale is worthless to a manager without knowing why. */
    public function requiresReason(): bool
    {
        return $this === self::NoSale;
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
