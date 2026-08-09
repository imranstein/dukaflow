<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/*
 * Pest's arch rules enumerate classes through composer's PSR-4 map and see
 * imports and static references. Two things cross a module boundary without
 * ever appearing as either: a migration, which is an anonymous class with no
 * namespace, and a raw query against a table another module owns.
 *
 * ADR-001 puts migrations first in the list of things a module owns, so both
 * gaps are checked here by reading the files.
 */

/**
 * The modules directory, found from this file rather than through app_path().
 * These tests read source; they do not need a booted application, and should
 * not fail differently depending on whether one happens to be up.
 */
function modulesPath(string $within = ''): string
{
    return dirname(__DIR__, 2).'/app/Modules'.($within === '' ? '' : '/'.$within);
}

/** Every table, and the module that owns it. */
function tableOwners(): array
{
    return [
        'Catalog' => ['products', 'units_of_measure', 'price_lists', 'price_list_items', 'price_list_assignments'],
        'Distribution' => ['customers', 'routes', 'sales_reps', 'visit_schedules'],
        'Inventory' => ['warehouses', 'stock_movements', 'stock_reconciliations', 'stock_reconciliation_lines'],
        'Orders' => ['orders', 'order_lines', 'order_payments'],
    ];
}

/** @return list<string> the modules that exist on disk */
function moduleNames(): array
{
    $names = [];

    foreach (Finder::create()->directories()->in(modulesPath())->depth(0) as $directory) {
        $names[] = $directory->getFilename();
    }

    sort($names);

    return $names;
}

/**
 * @return array<string, string> path => contents, for one module's files
 */
function moduleFiles(string $module, ?string $withinPath = null): array
{
    $finder = Finder::create()->files()->in(modulesPath($module))->name('*.php');

    if ($withinPath !== null) {
        $finder->path($withinPath);
    }

    $files = [];

    foreach ($finder as $file) {
        $files["{$module}/{$file->getRelativePathname()}"] = (string) $file->getContents();
    }

    return $files;
}

/** @return array<string, string> every module migration */
function moduleMigrationFiles(): array
{
    $files = [];

    foreach (moduleNames() as $module) {
        $files += moduleFiles($module, 'Database/Migrations');
    }

    return $files;
}

it('knows about every module on disk', function () {
    // If a module is added and this list is not, the checks below would pass
    // by simply not looking at it.
    expect(moduleNames())->toBe(array_keys(tableOwners()));
});

it('finds the module migrations', function () {
    expect(moduleMigrationFiles())->not->toBeEmpty();
});

it('keeps module migrations out of each other', function () {
    $violations = [];

    foreach (moduleNames() as $module) {
        foreach (moduleFiles($module, 'Database/Migrations') as $path => $contents) {
            foreach (moduleNames() as $other) {
                if ($other !== $module && str_contains($contents, "App\\Modules\\{$other}")) {
                    $violations[] = "{$path} references App\\Modules\\{$other}";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

it('holds module migrations to the same strict types rule as everything else', function () {
    // The arch rule cannot see these files either, for the same reason.
    $missing = [];

    foreach (moduleMigrationFiles() as $path => $contents) {
        if (! str_contains($contents, 'declare(strict_types=1);')) {
            $missing[] = $path;
        }
    }

    expect($missing)->toBe([]);
});

it('keeps module migrations off other modules tables', function () {
    // A foreign key into another module's table is the same coupling as an
    // import, just written in SQL.
    $violations = [];

    foreach (moduleNames() as $module) {
        foreach (moduleFiles($module, 'Database/Migrations') as $path => $contents) {
            foreach (tableOwners() as $owner => $tables) {
                if ($owner === $module) {
                    continue;
                }

                foreach ($tables as $table) {
                    // Named explicitly: ->constrained('customers')
                    if (preg_match("/constrained\(\s*'{$table}'/", $contents) === 1) {
                        $violations[] = "{$path} constrains against {$table}";
                    }

                    // Inferred from the column, which is how every migration
                    // in this repo is actually written:
                    // ->foreignId('customer_id')->constrained()
                    $column = Str::singular($table).'_id';

                    if (preg_match("/foreignId\(\s*'{$column}'\s*\)[^;]*->constrained\(\s*\)/s", $contents) === 1) {
                        $violations[] = "{$path} constrains against {$table} through {$column}";
                    }
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

it('keeps module code off other modules tables', function () {
    // DB::table('customers') from Catalog crosses the boundary as completely
    // as importing the model, and no arch rule can see it. Banning the DB
    // facade outright would be wrong, since DB::transaction is legitimate.
    $violations = [];

    foreach (moduleNames() as $module) {
        foreach (moduleFiles($module) as $path => $contents) {
            foreach (tableOwners() as $owner => $tables) {
                if ($owner === $module) {
                    continue;
                }

                foreach ($tables as $table) {
                    if (preg_match("/(DB::table|->table)\(\s*'{$table}'/", $contents) === 1) {
                        $violations[] = "{$path} queries {$table}, which belongs to {$owner}";
                    }
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

it('keeps one module from naming another in a string', function () {
    // app('App\Modules\Catalog\...') resolves a class without importing it,
    // so the arch rules never see it.
    $violations = [];

    foreach (moduleNames() as $module) {
        foreach (moduleFiles($module) as $path => $contents) {
            foreach (moduleNames() as $other) {
                if ($other === $module) {
                    continue;
                }

                if (preg_match("/['\"]App\\\\\\\\?Modules\\\\\\\\?{$other}/", $contents) === 1) {
                    $violations[] = "{$path} names App\\Modules\\{$other} in a string";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
