<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;

it('casts is_active to a boolean', function (mixed $stored, bool $expected) {
    expect((new Product(['is_active' => $stored]))->is_active)->toBe($expected);
})->with([
    'integer one' => [1, true],
    'integer zero' => [0, false],
    'string one' => ['1', true],
    'string zero' => ['0', false],
]);
