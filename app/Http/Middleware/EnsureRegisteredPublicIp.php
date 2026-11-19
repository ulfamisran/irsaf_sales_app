<?php

namespace App\Http\Middleware;

use App\Services\UserPublicIpAccess;
use App\Support\ClientIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisteredPublicIp
{
    private const PREFIXES = [
        'sales.',
        'purchases.',
        'services.',
        'rentals.',
        'cash-flows.',
        'incoming-goods.',
        'stock-mutations.',
        'damaged-goods.',
        'purchase-receiving.',
        'mutasi-dana.',
        'saldo-adjustment.',
    ];

    public function __construct(
        private UserPublicIpAccess $access
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || ! $this->guardsTransaction($request)) {
            return $next($request);
        }

        $user = $request->user();
        $ip = ClientIp::resolve($request);
        if (! $user || $this->access->allows($user, $ip)) {
            return $next($request);
        }

        $message = __('Perangkat ini tidak terdaftar untuk mencatat transaksi. IP publik saat ini: :ip', [
            'ip' => $ip ?: __('tidak dikenali'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }

    private function guardsTransaction(Request $request): bool
    {
        $name = (string) $request->route()?->getName();
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
