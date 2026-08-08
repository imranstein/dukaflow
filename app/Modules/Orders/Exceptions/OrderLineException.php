<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use DomainException;

final class OrderLineException extends DomainException
{
    public static function unknownProduct(int $productId): self
    {
        return new self("There is no product with id {$productId}.");
    }

    public static function productNotForSale(string $name): self
    {
        return new self("[{$name}] is no longer sold and cannot be added to an order.");
    }

    public static function unpriced(string $name): self
    {
        return new self(
            "No price list in force prices [{$name}] for this customer, so it cannot be ordered."
        );
    }

    public static function quantityMustBePositive(int $quantity): self
    {
        return new self("A line quantity must be at least 1, not {$quantity}.");
    }

    public static function wrongCurrency(string $name, string $priced, string $order): self
    {
        return new self(
            "[{$name}] is priced in {$priced} but the order is in {$order}."
        );
    }
}
