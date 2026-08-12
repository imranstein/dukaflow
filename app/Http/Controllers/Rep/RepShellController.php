<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rep;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders once, then the service worker and IndexedDB take over. Everything
 * after this page load — the round, the visit, the order capture — runs in
 * the browser with no server round-trip, per
 * Docs/adr/0002-offline-sync-strategy.md §5 ("Livewire is dead offline").
 */
class RepShellController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('rep.shell', [
            'repName' => $request->user()?->name,
        ]);
    }
}
