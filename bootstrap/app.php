<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Fix: unauthenticated API requests return 401 JSON, not redirect to login
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        // Catch-all: log every exception + return detailed JSON for API routes
        $exceptions->render(function (\Throwable $e, Request $request) {
            // Skip AuthenticationException (handled above)
            if ($e instanceof AuthenticationException) {
                return null;
            }

            Log::error('Unhandled Exception', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->is('api/*') || $request->expectsJson()) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                $response = [
                    'message' => $e->getMessage() ?: 'Server Error',
                    'status' => $status,
                ];

                if (config('app.debug')) {
                    $response['file'] = $e->getFile();
                    $response['line'] = $e->getLine();
                    $response['trace'] = collect($e->getTrace())->take(10)->map(fn($t) => [
                        'file' => $t['file'] ?? null,
                        'line' => $t['line'] ?? null,
                        'function' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
                    ])->toArray();
                }

                return response()->json($response, $status);
            }
        });
    })->create();
