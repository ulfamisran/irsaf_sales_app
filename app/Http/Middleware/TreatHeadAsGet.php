<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Beberapa probe / reverse proxy mengirim HEAD. Kalau aplikasi atau dependensi
 * hanya meng-handle GET, HEAD bisa memicu error. Middleware ini menjalankan
 * request sebagai GET lalu menghapus body respons (sesuai perilaku HEAD).
 */
class TreatHeadAsGet
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $request->setMethod('GET');

        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        if (method_exists($response, 'setContent')) {
            $response->setContent('');
        }

        return $response;
    }
}
