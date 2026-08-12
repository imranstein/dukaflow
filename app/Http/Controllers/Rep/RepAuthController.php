<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rep;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The rep interface's own login, separate from the back office's. Both sit
 * on the same session guard and the same users table — this exists because
 * the PWA is its own product surface a rep should never have to leave, not
 * because auth itself differs.
 */
class RepAuthController extends Controller
{
    public function create(): View
    {
        return view('rep.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Always remembered: a rep offline for a working day outlives the
        // default session, and the manual sync button is the fallback, not
        // a login prompt mid-round. See
        // Docs/adr/0002-offline-sync-strategy.md §8.
        if (! Auth::attempt($credentials, remember: true)) {
            throw ValidationException::withMessages([
                'email' => 'Those details do not match a rep account.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('rep.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('rep.login');
    }
}
