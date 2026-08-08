<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * How the money arrived.
 *
 * Two options, and there will not be a third from a payment processor. The
 * brief rules out gateway integrations outright; a payment here is a note
 * that cash changed hands or that the outlet was given terms.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Credit => 'On credit',
        };
    }

    /** Credit is a promise, not money in the tin, so it is not counted. */
    public function settlesImmediately(): bool
    {
        return $this === self::Cash;
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
