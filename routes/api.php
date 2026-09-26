<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Logis\LogisContext\Infrastructure\Http\Controller\AuthController;
use Logis\LogisContext\Infrastructure\Http\Controller\SearchController;
use Logis\LogisContext\Infrastructure\Http\Controller\ViewController;
use Logis\LogisContext\Infrastructure\Http\Middleware\RotateAccessTokenMiddleware;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
        Route::post('login', 'login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', 'logout');
            Route::post('refresh', 'refresh');
        });
    });

    Route::middleware(['auth:sanctum', RotateAccessTokenMiddleware::class])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('search', [SearchController::class, 'search']);

        Route::prefix('views')->controller(ViewController::class)->where(['id' => '[1-9][0-9]{0,17}'])->group(function (): void {
            Route::get('', 'index');
            Route::post('', 'store');
            Route::get('{id}', 'show');
            Route::put('{id}', 'update');
            Route::delete('{id}', 'destroy');
            Route::post('{id}/execute', 'execute');
            Route::post('{id}/set-default', 'setDefault');
        });
    });
});
