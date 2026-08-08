<?php

declare(strict_types=1);

arch('no debugging helpers are left behind')
    ->expect(['dd', 'ddd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();
