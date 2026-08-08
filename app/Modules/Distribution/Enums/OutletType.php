<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Enums;

/**
 * The kinds of outlet a distributor sells to. Order size and visit frequency
 * differ enormously between them, which is why it is worth recording.
 */
enum OutletType: string
{
    case Kiosk = 'kiosk';
    case Supermarket = 'supermarket';
    case Restaurant = 'restaurant';
    case Hotel = 'hotel';
    case Wholesaler = 'wholesaler';
    case Pharmacy = 'pharmacy';

    public function label(): string
    {
        return match ($this) {
            self::Kiosk => 'Kiosk',
            self::Supermarket => 'Supermarket',
            self::Restaurant => 'Restaurant or cafe',
            self::Hotel => 'Hotel',
            self::Wholesaler => 'Wholesaler',
            self::Pharmacy => 'Pharmacy',
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
