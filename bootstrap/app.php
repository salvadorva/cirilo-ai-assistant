<?php

use App\Http\Middleware\CheckDailyImageLimit;
use App\Http\Middleware\CheckFeatureEnabled;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\GameProgressMiddleware;
use App\Http\Middleware\RefreshCsrfToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Registrar middleware global para refrescar token CSRF
        $middleware->web([
            RefreshCsrfToken::class,
            GameProgressMiddleware::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'feature' => CheckFeatureEnabled::class,
            'image.limit' => CheckDailyImageLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
