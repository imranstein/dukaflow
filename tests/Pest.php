<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
 * Fixed ids for the ledger tests. These live here rather than as constants in
 * one test file, because a constant defined in a sibling only exists when that
 * sibling happens to be loaded: running the reconciliation tests on their own
 * failed with "Undefined constant REP", and a second file defining the same
 * name would have collided silently.
 *
 * They are deliberately not real records. The ledger references products,
 * warehouses and reps by bare id across a module boundary, so it never loads
 * them, and the tests are honest about that.
 */

function warehouseId(): int
{
    return 1;
}

function repId(): int
{
    return 7;
}

function productId(): int
{
    return 42;
}
