<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum ReconciliationStatus: string
{
    /** Being counted. Lines can still be entered and corrected. */
    case Open = 'open';

    /** Accepted. The adjustments have been written and it is now history. */
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
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
