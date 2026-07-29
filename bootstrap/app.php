<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render all API failures through the uniform response envelope so
        // every endpoint returns a predictable JSON shape and never leaks
        // stack traces or internal details in production.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error(
                    'The given data was invalid.',
                    422,
                    $e->errors(),
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    'Unauthenticated.',
                    401,
                ),
                $e instanceof AuthorizationException => ApiResponse::error(
                    'This action is unauthorized.',
                    403,
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'Resource not found.',
                    404,
                ),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: 'HTTP error.',
                    $e->getStatusCode(),
                ),
                default => ApiResponse::error(
                    config('app.debug') ? $e->getMessage() : 'Server error.',
                    500,
                ),
            };
        });
    })->create();
