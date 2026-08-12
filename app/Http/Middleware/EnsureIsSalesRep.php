<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Contracts\RepDirectory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate on the rep interface: an authenticated user who is not a sales rep
 * has nothing to do here, whatever their back-office role is.
 */
class EnsureIsSalesRep
{
    public function __construct(private readonly RepDirectory $reps) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id;
        $repId = $userId === null ? null : $this->reps->repIdForUser($userId);

        if ($repId === null) {
            abort(403, 'This area is for sales reps.');
        }

        return $next($request);
    }
}
