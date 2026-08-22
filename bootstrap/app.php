<?php

use App\Exceptions\ApiProblemException;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\RequireSubscriptionCapability;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['subscription' => RequireSubscriptionCapability::class]);
        $middleware->append(AddSecurityHeaders::class);
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $json = fn (string $message, string $code, array $errors, int $status) => response()->json([
            'status' => 'error',
            'message' => $message,
            'error_code' => $code,
            'errors' => $errors,
        ], $status);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

        $exceptions->render(fn (ApiProblemException $e, Request $request) => $request->is('api/*') ? $json($e->getMessage(), $e->errorCode, $e->errors, $e->httpStatus) : null);
        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*') ? $json('Data yang diberikan tidak valid.', 'VALIDATION_ERROR', $e->errors(), 422) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*') ? $json('Unauthenticated.', 'UNAUTHENTICATED', [], 401) : null);
        $exceptions->render(fn (AuthorizationException $e, Request $request) => $request->is('api/*') ? $json('Forbidden.', 'FORBIDDEN', [], 403) : null);
        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $request->is('api/*') ? $json('Resource tidak ditemukan.', 'NOT_FOUND', [], 404) : null);
        $exceptions->render(fn (HttpResponseException $e, Request $request) => $request->is('api/*') ? $e->getResponse() : null);
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($json) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'HTTP_ERROR',
            };
            $message = match ($status) {
                401 => 'Unauthenticated.',
                403 => 'Forbidden.',
                404 => 'Resource tidak ditemukan.',
                default => $e->getMessage(),
            };

            return $json($message, $code, [], $status);
        });
        $exceptions->render(function (Throwable $e, Request $request) use ($json) {
            if (! $request->is('api/*')) {
                return null;
            }

            report($e);

            return $json('Terjadi kesalahan internal.', 'INTERNAL_ERROR', [], 500);
        });
    })->create();
