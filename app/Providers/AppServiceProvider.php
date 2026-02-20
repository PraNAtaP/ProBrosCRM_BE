<?php

namespace App\Providers;

use App\Models\Deal;
use App\Observers\DealObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Deal::observe(DealObserver::class);

        // Database health check — log connection failures clearly
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            Log::critical('DATABASE CONNECTION FAILED', [
                'driver' => config('database.default'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
                'database' => config('database.connections.' . config('database.default') . '.database'),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
