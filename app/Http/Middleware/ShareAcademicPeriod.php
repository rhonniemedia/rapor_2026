<?php

namespace App\Http\Middleware;

use App\Services\AcademicPeriod;
use App\Services\Rapor\RaporAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Shares the semester being viewed and the role of the user with every view (topbar, sidebar). */
class ShareAcademicPeriod
{
    public function handle(Request $request, Closure $next): Response
    {
        $period = app(AcademicPeriod::class);
        $access = app(RaporAccess::class);
        $user = $request->user();

        View::share('currentAcademicPeriod', $period->current());
        View::share('academicPeriodOptions', $period->options());
        View::share('isAcademicPeriodOverridden', $period->isOverridden());
        View::share('raporRole', $user ? $access->role($user) : null);

        return $next($request);
    }
}
