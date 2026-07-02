<?php

use App\Http\Middleware\Authenticate as AuthenticateMiddleware;
use App\Http\Middleware\EarlyCorsMiddleware;
use App\Http\Middleware\TrustProxies as AppTrustProxies;
use App\Providers\CloudinaryServiceProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withProviders([
        CloudinaryServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => AuthenticateMiddleware::class,
            'auth.basic' => AuthenticateMiddleware::class,
        ]);
        $middleware->prepend(EarlyCorsMiddleware::class);
        $middleware->replace(TrustProxies::class, AppTrustProxies::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })->create();
