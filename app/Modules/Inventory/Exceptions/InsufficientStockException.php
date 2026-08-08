<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Inventory\Enums\LocationType;
use DomainException;

/**
 * Thrown when a movement would take a balance below zero.
 *
 * The only way past this is an adjustment, which says outright that the books
 * were wrong rather than quietly making the number fit.
 */
final class InsufficientStockException extends DomainException
{
    public static function forProduct(
        int $productId,
        LocationType $locationType,
        int $locationId,
        int $available,
        int $requested,
    ): self {
        $place = $locationType === LocationType::Van
            ? "van {$locationId}"
            : "warehouse {$locationId}";

        return new self(
            "Product {$productId} has {$available} in {$place}, which is not enough to take {$requested}. "
            .'Record an adjustment if the books are wrong.'
        );
    }
}
