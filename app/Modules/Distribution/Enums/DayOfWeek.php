<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Enums;

use Illuminate\Support\Carbon;

/**
 * ISO-8601 day numbering, matching Carbon's dayOfWeekIso, so a schedule row
 * can be compared to a date without translating between two conventions.
 */
enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public static function for(Carbon $date): self
    {
        return self::from($date->dayOfWeekIso);
    }

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Monday',
            self::Tuesday => 'Tuesday',
            self::Wednesday => 'Wednesday',
            self::Thursday => 'Thursday',
            self::Friday => 'Friday',
            self::Saturday => 'Saturday',
            self::Sunday => 'Sunday',
        };
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
