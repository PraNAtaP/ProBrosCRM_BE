<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DealController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/deals/statuses', [DealController::class, 'statuses']);

// Protected routes (require Sanctum auth)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Dashboard
    Route::get('/dashboard-stats', [DashboardController::class, 'index']);
    
    // CRUD Resources
    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('deals', DealController::class);
    
    // Deal activities (timeline)
    Route::get('deals/{deal}/activities', [ActivityLogController::class, 'index']);
    Route::post('deals/{deal}/activities', [ActivityLogController::class, 'store']);
    
    // Manual activity logging & reports
    Route::post('activity-logs', [ActivityLogController::class, 'storeManual']);
    Route::get('reports', [ActivityLogController::class, 'reports']);
    
    // Commissions
    Route::get('commissions', [CommissionController::class, 'index']);
    Route::get('commissions/summary', [CommissionController::class, 'summary']);
    Route::get('commissions/{commission}', [CommissionController::class, 'show']);
    Route::patch('commissions/{commission}/pay', [CommissionController::class, 'markAsPaid']);
    
    // Areas (Publicly readable for filters)
    Route::get('/areas', [AreaController::class, 'index']);

    // Admin only routes
    Route::middleware('admin')->group(function () {
        Route::apiResource('areas', AreaController::class)->except(['index']);
    });
});
