<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who can do what in the back office.
 *
 * Deliberately a column on the user rather than a permissions package. Three
 * fixed roles is the whole requirement, and a role table with a permission
 * table and a pivot would be more machinery than the problem deserves. If the
 * roles ever need to be editable by the customer, that is the moment to bring
 * a package in.
 */
enum UserRole: string
{
    /** Runs the distributor. Can do anything, including deleting records. */
    case Admin = 'admin';

    /** Runs the sales operation. Maintains the catalogue and the field data. */
    case Manager = 'manager';

    /** Field staff. Works in the PWA; the back office is read-only to them. */
    case Rep = 'rep';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Rep => 'Sales rep',
        };
    }

    public function canReadBackOffice(): bool
    {
        return true;
    }

    public function canWriteBackOffice(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }

    public function canDeleteRecords(): bool
    {
        return $this === self::Admin;
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
