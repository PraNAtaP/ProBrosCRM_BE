<?php

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
        // Sanctum stateful domains (for SPA)
        $middleware->statefulApi();
        
        // Admin middleware alias
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Return 401 JSON for unauthenticated API requests instead of
        // trying to redirect to a "login" route that doesn't exist.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                abort(response()->json(['message' => 'Unauthenticated.'], 401));
            }
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force detailed JSON errors on API routes
        $exceptions->render(function (\Throwable $e, Request $request) {
            // Always log the error to stderr for Railway visibility
            Log::error('Unhandled Exception', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return detailed JSON on API requests when debug is on
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

