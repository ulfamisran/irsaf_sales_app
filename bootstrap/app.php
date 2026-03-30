<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\TreatHeadAsGet::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // HEAD ditangani di TreatHeadAsGet (global prepend) agar dijalankan seperti GET.
        // Jika 500 hanya untuk HEAD di balik Nginx/Apache, pastikan proxy meneruskan
        // HEAD ke PHP (atau gunakan proxy_set_header / mod_rewrite sesuai server).
    })->create();
