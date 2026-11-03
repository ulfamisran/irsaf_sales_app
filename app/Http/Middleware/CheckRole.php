<?php

namespace App\Http\Middleware;

use App\Services\ModuleAccessService;
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

        $access = app(ModuleAccessService::class);
        if ($access->tablesReady()) {
            $resolved = $access->resolve($request);
            if ($resolved !== null) {
                if (! $access->allows($request->user(), $resolved['module'], $resolved['action'])) {
                    abort(403, __('Anda tidak punya akses untuk aksi ini.'));
                }

                return $next($request);
            }
        }

        if ($request->user()->isAdminPusat()) {
            $routeName = $request->route()?->getName();
            $isStrictSuperAdminOnlyRoute = $routeName
                && (str_starts_with($routeName, 'landing-page.')
                    || str_starts_with($routeName, 'users.')
                    || str_starts_with($routeName, 'daily-reports.'));
            if (! $isStrictSuperAdminOnlyRoute) {
                return $next($request);
            }
        }

        // Normalize: handle both multiple args and single comma-separated string
        $roleList = [];
        foreach ($roles as $r) {
            foreach (array_map('trim', explode(',', $r)) as $part) {
                if ($part !== '') {
                    $roleList[] = $part;
                }
            }
        }
        $roleList = array_unique($roleList);

        if (empty($roleList) || ! $request->user()->hasAnyRole($roleList)) {
            abort(403, __('Unauthorized.'));
        }

        return $next($request);
    }
}
