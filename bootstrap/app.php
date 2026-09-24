<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Logis\SharedContext\Infrastructure\Http\ApiExceptionRenderer;
use Logis\SharedContext\Infrastructure\Http\Middleware\ExecutionTimeMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: [
        __DIR__.'/../app/LogisContext/Infrastructure/Listeners',
        __DIR__.'/../app/LogisContext/Infrastructure/AsyncListeners',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(ExecutionTimeMiddleware::class);
        $middleware->throttleApi();
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(ApiExceptionRenderer::expectedExceptions());
        $exceptions->render(
            fn (Throwable $exception, Request $request) => (new ApiExceptionRenderer())->render($exception, $request),
        );
    })->create();
