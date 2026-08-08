<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Contracts\ScopeDirectory;

/**
 * What you get when nothing has registered a directory.
 *
 * Keeps a module that asks for names usable on its own, in a test or an
 * install where the module that would answer is not present.
 */
final class NullScopeDirectory implements ScopeDirectory
{
    /** @return array<int, string> */
    public function options(string $scope): array
    {
        return [];
    }

    public function label(string $scope, int $id): ?string
    {
        return null;
    }

    public function handles(string $scope): bool
    {
        return false;
    }
}
