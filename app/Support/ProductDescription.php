<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What a product is, flattened to values.
 *
 * Orders copies these onto a line so the order still reads correctly after
 * the catalogue moves on. It is a plain value object rather than a Product
 * so that a module can learn about a product without depending on Catalog.
 */
final readonly class ProductDescription
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public ?string $unitCode,
        public int $packSize,
        public bool $isActive,
    ) {}
}
