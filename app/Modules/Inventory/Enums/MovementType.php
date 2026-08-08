<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * Why stock moved. The type is what makes a ledger readable months later.
 */
enum MovementType: string
{
    /** Stock arriving from a supplier into a warehouse. */
    case Receipt = 'receipt';

    /** Loaded from a warehouse onto a rep's van in the morning. */
    case VanLoad = 'van_load';

    /** Brought back and returned to the warehouse at night. */
    case VanReturn = 'van_return';

    /** Sold to an outlet against an order. */
    case Sale = 'sale';

    /**
     * A deliberate statement that the books were wrong.
     *
     * The only kind of movement allowed to take a balance negative, because
     * sometimes reality has already done so: breakage, theft, a bad count
     * that morning. See Docs/adr/0006-stock-ledger.md.
     */
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::VanLoad => 'Loaded to van',
            self::VanReturn => 'Returned from van',
            self::Sale => 'Sale',
            self::Adjustment => 'Adjustment',
        };
    }

    /** Adjustments are the only way a balance is allowed below zero. */
    public function mayGoNegative(): bool
    {
        return $this === self::Adjustment;
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
