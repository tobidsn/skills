<?php

use App\Services\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SecureHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'suspend.check' => \App\Http\Middleware\CheckSuspension::class,
            'twofactor' => \App\Http\Middleware\TwoFactorMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'upload/*',
            'media/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getMessage();
                $error = $e->errors();

                return ApiResponse::validationError($message, $error);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::messageOnly('Unauthorized access', 401);
            }
        });

        $exceptions->render(function (Exception $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::messageOnly($e->getMessage(), 500);
            }
        });
    })->create();
