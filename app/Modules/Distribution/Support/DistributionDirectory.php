<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Support;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;

/**
 * Answers "what outlets, routes and reps are there, and what are they called"
 * for any module that needs to refer to them without depending on this one.
 */
final class DistributionDirectory implements ScopeDirectory
{
    public function handles(string $scope): bool
    {
        return in_array($scope, [
            Scope::Customer->value,
            Scope::Route->value,
            Scope::SalesRep->value,
        ], strict: true);
    }

    /** @return array<int, string> */
    public function options(string $scope): array
    {
        /** @var array<int, string> $options */
        $options = match ($scope) {
            Scope::Customer->value => Customer::query()->orderBy('name')->pluck('name', 'id')->all(),
            Scope::Route->value => Route::query()->orderBy('name')->pluck('name', 'id')->all(),
            Scope::SalesRep->value => SalesRep::query()->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };

        return $options;
    }

    public function label(string $scope, int $id): ?string
    {
        $name = match ($scope) {
            Scope::Customer->value => Customer::query()->whereKey($id)->value('name'),
            Scope::Route->value => Route::query()->whereKey($id)->value('name'),
            Scope::SalesRep->value => SalesRep::query()->whereKey($id)->value('name'),
            default => null,
        };

        return is_string($name) ? $name : null;
    }
}
