<?php

declare(strict_types=1);

use App\Modules\Sync\Http\Controllers\SyncPricebookController;
use App\Modules\Sync\Http\Controllers\SyncPullController;
use App\Modules\Sync\Http\Controllers\SyncPushController;
use Illuminate\Support\Facades\Route;

// Same-origin, session-guarded, standard CSRF — see
// Docs/adr/0002-offline-sync-strategy.md §8 for why this needed no new
// package and no separate API guard. Under /api so the app's existing
// shouldRenderJsonWhen() renders these as JSON rather than a login redirect.
Route::middleware(['web', 'auth'])->prefix('api/sync')->name('sync.')->group(function (): void {
    Route::post('push', SyncPushController::class)->name('push');
    Route::get('pull', SyncPullController::class)->name('pull');
    Route::get('pricebook', SyncPricebookController::class)->name('pricebook');
});
