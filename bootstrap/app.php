<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureIsSalesRep;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['rep' => EnsureIsSalesRep::class]);

        // The framework default points every guest redirect at a plain
        // 'login' route this app has never had — the back office is
        // Filament's own auth, and the rep interface has its own front door
        // at rep.login. Nothing else currently reaches this fallback.
        $middleware->redirectGuestsTo(fn (Request $request): string => route('rep.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
