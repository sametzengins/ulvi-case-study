<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
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
    // Private channels are authorised at /api/broadcasting/auth with the API token.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Framework errors get the same shape as ApiException: {"message", "code", "errors"?, "meta"?}.
        $error = fn (string $code, int $status, array $extra = [], array $headers = []) => response()->json(
            ['message' => __("api.errors.{$code}"), 'code' => $code, ...$extra],
            $status,
            $headers,
        );

        $exceptions->render(function (ValidationException $e, Request $request) use ($error) {
            return $request->is('api/*') ? $error('validation_failed', 422, ['errors' => $e->errors()]) : null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($error) {
            return $request->is('api/*') ? $error('unauthenticated', 401) : null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($error) {
            $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            return $request->is('api/*')
                ? $error('rate_limited', 429, ['meta' => ['retry_after' => $retryAfter]], $e->getHeaders())
                : null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($error) {
            return $request->is('api/*') ? $error('not_found', 404) : null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($error) {
            return $request->is('api/*') && $e->getStatusCode() < 500
                ? $error('http_error', $e->getStatusCode(), [], $e->getHeaders())
                : null;
        });
    })->create();
