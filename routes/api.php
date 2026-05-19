<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\LandingController;
use App\Http\Controllers\Api\V1\Admin\ApiDashboardController;

Route::get('/health', fn () => response()->json(['status' => 'ok']))
    ->withoutMiddleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);

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

// TEMP: Debug ITAD store coverage
Route::get('/admin/itad-stores', function () {
    $scraper = new \App\Services\Scrapers\IsThereAnyDealScraper();
    if (!$scraper->isConfigured()) {
        return response()->json(['error' => 'ITAD not configured'], 500);
    }
    
    $shops = $scraper->getShops();
    $shopNames = array_map(fn($s) => $s['name'], $shops);
    
    $target = ['Kinguin', 'G2A', 'Gamivo', 'Gamesplanet', 'G2A.COM', 'G2A Plus', 'Kinguin.net'];
    $found = array_filter($shopNames, fn($name) => 
        stripos($name, 'kinguin') !== false ||
        stripos($name, 'g2a') !== false ||
        stripos($name, 'gamivo') !== false ||
        stripos($name, 'gamesplanet') !== false
    );
    
    return response()->json([
        'total_shops' => count($shops),
        'matching_shops' => array_values($found),
        'all_shops' => array_values($shopNames),
    ]);
})->middleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);
