<?php

namespace App\Http\Middleware;

use App\Services\UserActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function __construct(
        private UserActivityLogger $logger
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->logger->watch();
        $response = $next($request);
        $this->logger->recordRequest($request, $response);

        return $response;
    }
}
