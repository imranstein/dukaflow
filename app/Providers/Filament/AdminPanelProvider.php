<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Widgets\OrdersByRoute;
use App\Filament\Widgets\StockPosition;
use App\Filament\Widgets\TradingOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('DukaFlow')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // Each module owns its Filament resources, so the panel is told
            // where to look once per module. See Docs/adr/0001-module-boundaries.md.
            ->discoverResources(
                in: app_path('Modules/Catalog/Filament/Resources'),
                for: 'App\Modules\Catalog\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Distribution/Filament/Resources'),
                for: 'App\Modules\Distribution\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Orders/Filament/Resources'),
                for: 'App\Modules\Orders\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Inventory/Filament/Resources'),
                for: 'App\Modules\Inventory\Filament\Resources',
            )
            ->navigationGroups(['Trading', 'Catalogue', 'Distribution', 'Inventory'])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Widgets sit above the modules because they read across them,
            // which is knowledge the modules themselves are not allowed to
            // hold. See Docs/adr/0001-module-boundaries.md.
            ->widgets([
                TradingOverview::class,
                OrdersByRoute::class,
                StockPosition::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
