<?php

namespace App\Http\Middleware;

use App\Services\Rapor\RaporAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a logged-in user through when the shared role table gives them ANY role (superadmin, admin or user)
 * for this app. What they may do inside is decided by RaporAccess / Policies.
 */
class AuthorizeAppAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not logged in: the 'auth' middleware handles it.
        if (! $user) {
            return $next($request);
        }

        if (app(RaporAccess::class)->hasAccess($user)) {
            return $next($request);
        }

        return response()->view('pages.auth.forbidden', [], 403);
    }
}
