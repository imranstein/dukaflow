<?php

declare(strict_types=1);

arch('no debugging helpers are left behind')
    ->expect(['dd', 'ddd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

/*
 * The module boundary from docs/adr/0001-module-boundaries.md, enforced rather
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

arch('enums carry no framework dependencies')
    ->expect('App\Modules\Catalog\Enums')
    ->not->toUse('Illuminate\Database');
