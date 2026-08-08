<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * What a price list can be attached to.
 *
 * These are stored as strings rather than as a relation because the things
 * they point at (customers, routes) live in the Distribution module, which
 * Catalog does not depend on.
 */
enum PriceListScope: string
{
    case Customer = 'customer';
    case Route = 'route';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Route => 'Route',
        };
    }

    /**
     * Narrower scopes win when more than one price list could apply.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::Customer => 10,
            self::Route => 20,
        };
    }
}
