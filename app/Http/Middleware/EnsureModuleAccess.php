<?php

namespace App\Http\Middleware;

use App\Services\ModuleAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(
        private ModuleAccessService $access
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $resolved = $this->access->resolve($request);
        if ($resolved === null) {
            return $next($request);
        }

        if (! $this->access->allows($user, $resolved['module'], $resolved['action'])) {
            abort(403, __('Anda tidak punya akses untuk aksi ini.'));
        }

        return $next($request);
    }
}
