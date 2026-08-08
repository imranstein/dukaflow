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

arch('catalog does not reach into distribution')
    ->expect('App\Modules\Catalog')
    ->not->toUse('App\Modules\Distribution');

arch('distribution does not reach into catalog')
    ->expect('App\Modules\Distribution')
    ->not->toUse('App\Modules\Catalog');

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
 * cross the boundary just as thoroughly. ModuleMigrationBoundaryTest covers
 * migrations; these cover the rest of the module source.
 */

arch('no module resolves another module by class name string')
    ->expect('App\Modules')
    ->not->toUse('Illuminate\Support\Facades\App');

arch('no module reaches another module through the query builder')
    ->expect('App\Modules')
    ->not->toUse('Illuminate\Support\Facades\DB');
