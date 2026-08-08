<?php

declare(strict_types=1);

namespace App\Support\Contracts;

/**
 * Looks up records that one module needs to name but does not own.
 *
 * Catalog attaches price lists to customers and routes, which belong to
 * Distribution. To draw that form Catalog needs a list of outlets and their
 * names, and it is not allowed to ask Distribution's models for them.
 *
 * So this sits in the shared kernel and speaks only in primitives: Catalog
 * depends on the interface, Distribution provides the implementation, and
 * neither one imports the other. It is the "explicit service interface" that
 * docs/adr/0001-module-boundaries.md requires modules to talk through.
 */
interface ScopeDirectory
{
    /**
     * Every record of the given kind, as id to display name.
     *
     * @return array<int, string>
     */
    public function options(string $scope): array;

    /** The display name of one record, or null when it no longer exists. */
    public function label(string $scope, int $id): ?string;

    /** Whether this directory knows anything about the given kind of record. */
    public function handles(string $scope): bool;
}
