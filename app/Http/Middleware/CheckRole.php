<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  array<string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->isStrictSuperAdmin()) {
            return $next($request);
        }

        if ($request->user()->isAdminPusat()) {
            $routeName = $request->route()?->getName();
            $isSettingsRoute = $routeName
                && (str_starts_with($routeName, 'landing-page.')
                    || str_starts_with($routeName, 'users.'));
            if (! $isSettingsRoute) {
                return $next($request);
            }
        }

        if (! $request->user()->hasAnyRole($roles)) {
            abort(403, __('Unauthorized.'));
        }

        return $next($request);
    }
}
