<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Pest's arch rules enumerate classes through composer's PSR-4 map. Migrations
 * declare no namespace and return an anonymous class, so they are invisible to
 * `expect('App\Modules\Catalog')->not->toUse(...)` — a migration could import
 * another module's model and every arch rule would still pass.
 *
 * ADR-001 puts migrations first in the list of things a module owns, so the
 * boundary has to be checked on them too. This reads the files.
 */

/** @return array<string, string> path => contents */
function moduleMigrations(): array
{
    $files = [];

    foreach (Finder::create()->files()->in(app_path('Modules'))->path('Database/Migrations')->name('*.php') as $file) {
        $files[$file->getRelativePathname()] = (string) $file->getContents();
    }

    return $files;
}

it('finds the module migrations', function () {
    expect(moduleMigrations())->not->toBeEmpty();
});

it('keeps module migrations out of each other', function () {
    $modules = ['Catalog', 'Distribution'];
    $violations = [];

    foreach (moduleMigrations() as $path => $contents) {
        $owner = str_contains($path, 'Catalog') ? 'Catalog' : 'Distribution';

        foreach ($modules as $other) {
            if ($other === $owner) {
                continue;
            }

            if (str_contains($contents, "App\\Modules\\{$other}")) {
                $violations[] = "{$path} references App\\Modules\\{$other}";
            }
        }
    }

    expect($violations)->toBe([]);
});

it('holds module migrations to the same strict types rule as everything else', function () {
    // The arch rule cannot see these files either, for the same reason.
    $missing = [];

    foreach (moduleMigrations() as $path => $contents) {
        if (! str_contains($contents, 'declare(strict_types=1);')) {
            $missing[] = $path;
        }
    }

    expect($missing)->toBe([]);
});

it('keeps module migrations off other modules tables', function () {
    // A foreign key into another module's table is the same coupling as an
    // import, just expressed in SQL. Catalog must not constrain against
    // customers or routes, and Distribution must not constrain against
    // products or price lists.
    $forbidden = [
        'Catalog' => ['customers', 'routes', 'sales_reps', 'visit_schedules'],
        'Distribution' => ['products', 'price_lists', 'price_list_items', 'units_of_measure'],
    ];

    $violations = [];

    foreach (moduleMigrations() as $path => $contents) {
        $owner = str_contains($path, 'Catalog') ? 'Catalog' : 'Distribution';

        foreach ($forbidden[$owner] as $table) {
            if (preg_match("/constrained\(\s*'{$table}'/", $contents) === 1) {
                $violations[] = "{$path} constrains against {$table}";
            }
        }
    }

    expect($violations)->toBe([]);
});
