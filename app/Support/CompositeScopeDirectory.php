<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Contracts\ScopeDirectory;

/**
 * The one directory everything asks, made of the directories each module
 * contributes.
 *
 * Phase 1 had a single implementation bound, which worked while only
 * Distribution had anything to offer. Orders needs to name products as well
 * as outlets, and those live in different modules, so the binding became a
 * composite: modules register what they know about and the lookup finds the
 * one that answers.
 *
 * A composite with nothing registered answers nothing, which is the correct
 * behaviour for an install missing the module that would have answered.
 */
final class CompositeScopeDirectory implements ScopeDirectory
{
    /** @var list<ScopeDirectory> */
    private array $directories = [];

    public function register(ScopeDirectory $directory): void
    {
        $this->directories[] = $directory;
    }

    public function handles(string $scope): bool
    {
        return $this->directoryFor($scope) instanceof ScopeDirectory;
    }

    /** @return array<int, string> */
    public function options(string $scope): array
    {
        return $this->directoryFor($scope)?->options($scope) ?? [];
    }

    public function label(string $scope, int $id): ?string
    {
        return $this->directoryFor($scope)?->label($scope, $id);
    }

    /** @return list<string> every scope any registered directory answers for */
    public function scopes(): array
    {
        $scopes = [];

        foreach (Scope::cases() as $scope) {
            if ($this->handles($scope->value)) {
                $scopes[] = $scope->value;
            }
        }

        return $scopes;
    }

    private function directoryFor(string $scope): ?ScopeDirectory
    {
        foreach ($this->directories as $directory) {
            if ($directory->handles($scope)) {
                return $directory;
            }
        }

        return null;
    }
}
