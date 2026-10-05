<?php

use App\Http\Middleware\ApplyMockClock;
use App\Http\Middleware\EnsureNotProduction;
use App\Http\Middleware\InjectFault;
use App\Http\Middleware\RecordRequest;
use App\Http\Middleware\RequireApiKey;
use App\Http\Middleware\VerifyConsole;
use App\Mock\Errors\ErrorEnvelope;
use App\Mock\Errors\MockError;
use App\Mock\Unverified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // R4: both groups under `api` (no CSRF, so no 419), no /api prefix, nothing under web.
        // R11: middleware order is part of the contract.
        then: function () {
            Route::middleware(['api', ApplyMockClock::class, VerifyConsole::class, RequireApiKey::class, InjectFault::class])
                ->prefix(config('unifi_mock.integration_prefix'))
                ->group(base_path('routes/integration.php'));

            Route::middleware(['api', ApplyMockClock::class, EnsureNotProduction::class])
                ->prefix('__mock')
                ->group(base_path('routes/control.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, so unknown paths and refusals are logged too.
        $middleware->prepend(RecordRequest::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([MockError::class, ValidationException::class]);

        // R5: one envelope for every error, whatever the Accept header says.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpResponseException) {
                return null;
            }

            [$status, $code, $message] = match (true) {
                $e instanceof MockError => [$e->status, $e->errorCode, $e->getMessage()],
                $e instanceof ValidationException => [400, Unverified::ERROR_CODES['request_invalid'], implode(' ', array_merge(...array_values($e->errors())))],
                $e instanceof NotFoundHttpException => [404, Unverified::ERROR_CODES['not_found'], 'The path is not part of the UniFi Network API collection.'],
                $e instanceof MethodNotAllowedHttpException => [405, Unverified::ERROR_CODES['method_not_allowed'], 'Method not allowed for this path.'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), Unverified::ERROR_CODES['request_invalid'], $e->getMessage() ?: 'Request refused.'],
                default => [500, Unverified::ERROR_CODES['internal'], 'Internal mock error.'],
            };

            return ErrorEnvelope::response($request, $status, $code, $message);
        });
    })->create();
