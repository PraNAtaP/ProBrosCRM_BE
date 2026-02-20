<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Diagnostic: test if logs appear in Railway
Route::get('/test-error', function () {
    Log::error('TEST ERROR TRIGGERED — if you see this in Railway logs, logging is working.');
    throw new \Exception('Test Error from /test-error route — Railway logging verification');
});

// Diagnostic: DB connection health check
Route::get('/health-check', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'ok',
            'database' => 'connected',
            'driver' => config('database.default'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'log_channel' => config('logging.default'),
            'app_debug' => config('app.debug'),
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        Log::critical('Health check DB failure', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'database' => 'disconnected',
            'error' => $e->getMessage(),
        ], 500);
    }
});
