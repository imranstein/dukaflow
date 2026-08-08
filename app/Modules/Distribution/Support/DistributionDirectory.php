<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Support;

use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Support\Contracts\ScopeDirectory;

/**
 * Answers "what outlets and routes are there, and what are they called" for
 * any module that needs to refer to them without depending on this one.
 */
final class DistributionDirectory implements ScopeDirectory
{
    public const CUSTOMER = 'customer';

    public const ROUTE = 'route';

    public function handles(string $scope): bool
    {
        return in_array($scope, [self::CUSTOMER, self::ROUTE], strict: true);
    }

    /** @return array<int, string> */
    public function options(string $scope): array
    {
        /** @var array<int, string> $options */
        $options = match ($scope) {
            self::CUSTOMER => Customer::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            self::ROUTE => Route::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            default => [],
        };

        return $options;
    }

    public function label(string $scope, int $id): ?string
    {
        $name = match ($scope) {
            self::CUSTOMER => Customer::query()->whereKey($id)->value('name'),
            self::ROUTE => Route::query()->whereKey($id)->value('name'),
            default => null,
        };

        return is_string($name) ? $name : null;
    }
}
