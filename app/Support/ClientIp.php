<?php

namespace App\Support;

use Illuminate\Http\Request;

class ClientIp
{
    public static function resolve(?Request $request = null): ?string
    {
        $request ??= request();
        if ($request === null) {
            return null;
        }

        $direct = self::normalize($request->ip());
        if (self::isPublic($direct)) {
            return $direct;
        }

        foreach (['CF-Connecting-IP', 'X-Real-IP'] as $header) {
            $candidate = self::normalize(explode(',', (string) $request->headers->get($header, ''))[0] ?? '');
            if (self::isPublic($candidate)) {
                return $candidate;
            }
        }

        foreach (explode(',', (string) $request->headers->get('X-Forwarded-For', '')) as $part) {
            $candidate = self::normalize($part);
            if (self::isPublic($candidate)) {
                return $candidate;
            }
        }

        return $direct;
    }

    private static function normalize(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private static function isPublic(?string $ip): bool
    {
        return $ip !== null && filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
