<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The names modules use to refer to each other's records.
 *
 * These strings are the vocabulary of the cross-module contracts. They live in
 * the shared kernel rather than in the module that owns each record, because
 * the whole point is that the asking module does not depend on the answering
 * one. Distribution owns outlets; Orders only needs to know the word.
 */
enum Scope: string
{
    case Customer = 'customer';
    case Route = 'route';
    case SalesRep = 'sales_rep';
    case Product = 'product';
    case PriceList = 'price_list';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Outlet',
            self::Route => 'Route',
            self::SalesRep => 'Sales rep',
            self::Product => 'Product',
            self::PriceList => 'Price list',
            self::Warehouse => 'Warehouse',
        };
    }
}
