<?php

declare(strict_types=1);

arch('no debugging helpers are left behind')
    ->expect(['dd', 'ddd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

/*
 * The module boundary from Docs/adr/0001-module-boundaries.md, enforced rather
 * than merely written down. Modules cooperate through service classes that
 * take and return plain values; the moment one of them imports another's
 * models this fails, in CI, before review.
 */

arch('catalog does not reach into another module')
    ->expect('App\Modules\Catalog')
    ->not->toUse(['App\Modules\Distribution', 'App\Modules\Orders', 'App\Modules\Inventory']);

arch('distribution does not reach into another module')
    ->expect('App\Modules\Distribution')
    ->not->toUse(['App\Modules\Catalog', 'App\Modules\Orders', 'App\Modules\Inventory']);

/*
 * Orders is downstream of both Catalog and Distribution and still depends on
 * neither. It prices through the Pricebook contract, reads product details
 * through ProductCatalogue, names records through ScopeDirectory, and tells
 * Inventory about a fulfilment by raising an event. Every one of those is a
 * shared kernel type carrying primitives.
 */

arch('orders does not reach into another module')
    ->expect('App\Modules\Orders')
    ->not->toUse(['App\Modules\Catalog', 'App\Modules\Distribution', 'App\Modules\Inventory']);

arch('inventory does not reach into another module')
    ->expect('App\Modules\Inventory')
    ->not->toUse(['App\Modules\Catalog', 'App\Modules\Distribution', 'App\Modules\Orders']);

arch('the shared kernel depends on no module')
    ->expect('App\Support')
    ->not->toUse('App\Modules');

arch('catalog enums carry no framework dependencies')
    ->expect('App\Modules\Catalog\Enums')
    ->not->toUse('Illuminate\Database');

arch('distribution enums carry no framework dependencies')
    ->expect('App\Modules\Distribution\Enums')
    ->not->toUse('Illuminate\Database');

/*
 * Arch rules see imports and static references. They do not see a class named
 * in a string, or a table reached through the query builder, both of which
 * cross the boundary just as thoroughly. Banning the DB facade outright would
 * be wrong — OrderWriter needs DB::transaction — so the table-level check
 * lives in ModuleBoundaryTest, which reads the files.
 */
