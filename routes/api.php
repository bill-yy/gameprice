<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\LandingController;
use App\Http\Controllers\Api\V1\Admin\ApiDashboardController;

Route::get('/health', function () {
    $stores = array_keys([
        'eneba' => 1, 'instant-gaming' => 1, 'cheapshark' => 1,
        'g2a' => 1, 'kinguin' => 1, 'gamivo' => 1,
        'gamesplanet' => 1, 'allkeyshop' => 1,
        'cdkeys' => 1, 'psn-store' => 1, 'xbox-store' => 1,
    ]);
    
    $health = \App\Services\ScraperMonitor::getHealth($stores);
    $alerts = \App\Services\ScraperMonitor::getAlerts($stores);
    
    $downStores = array_filter($health, fn ($h) => $h['status'] === 'down');
    $overallStatus = count($downStores) > 0 ? 'degraded' : 'ok';
    
    return response()->json([
        'status' => $overallStatus,
        'version' => '1.0.2',
        'scrapers' => $health,
        'alerts' => $alerts,
        'timestamp' => now()->toIso8601String(),
    ]);
})->withoutMiddleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);

Route::get('/', [LandingController::class, 'index'])
    ->withoutMiddleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);

Route::prefix('v1')->group(function () {
    Route::get('/search', [SearchController::class, 'searchAll']);
    Route::get('/prices/{store}', [SearchController::class, 'searchByStore']);
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/deals', [SearchController::class, 'deals']);

    // Webhooks (Pro/Ultra only)
    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);

    // Admin dashboard
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [ApiDashboardController::class, 'stats']);
        Route::get('/keys', [ApiDashboardController::class, 'keys']);
        Route::post('/keys', [ApiDashboardController::class, 'createKey']);
        Route::delete('/keys/{id}', [ApiDashboardController::class, 'revokeKey']);
    });
});
