<?php

use App\Http\Middleware\VerifyHQToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Percayai header X-Forwarded-* dari proxy/load balancer (produksi).
        // APP_TRUSTED_PROXIES=127.0.0.1,RANGE 172.17.0.0/16
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) env('APP_TRUSTED_PROXIES', ''))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        $middleware->alias([
            'hq.token' => VerifyHQToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
