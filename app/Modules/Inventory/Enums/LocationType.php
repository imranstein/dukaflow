<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * Where stock sits.
 *
 * A warehouse is a row this module owns. A van is a rep, who belongs to
 * Distribution — which is why this is a kind and an id rather than two
 * foreign keys.
 */
enum LocationType: string
{
    case Warehouse = 'warehouse';
    case Van = 'van';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Warehouse',
            self::Van => 'Van',
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
