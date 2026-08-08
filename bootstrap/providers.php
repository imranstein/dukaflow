<?php

declare(strict_types=1);

use App\Modules\Catalog\CatalogServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CatalogServiceProvider::class,
];
