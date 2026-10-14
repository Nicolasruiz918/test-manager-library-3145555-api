<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(using: function (): void {
        Route::get('/health', static fn () => response()->json(['status' => 'ok']));
        Route::prefix('api/v1')->group(__DIR__.'/../routes/api.php');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove([
            Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static fn (Request $request, Throwable $error): bool => true);
        $exceptions->render(static fn (NotFoundHttpException $error) => response()->json([
            'code' => 'NOT_FOUND', 'message' => 'No se encontró el recurso.', 'details' => [],
        ], 404));
    })
    ->create();
