<?php

declare(strict_types=1);

use App\Http\Controllers\Rep\RepAuthController;
use App\Http\Controllers\Rep\RepShellController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('rep')->name('rep.')->group(function (): void {
    Route::get('login', [RepAuthController::class, 'create'])->middleware('guest')->name('login');
    Route::post('login', [RepAuthController::class, 'store'])->middleware('guest')->name('login.store');
    Route::post('logout', [RepAuthController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware(['auth', 'rep'])->group(function (): void {
        Route::get('/', RepShellController::class)->name('home');
    });
});
