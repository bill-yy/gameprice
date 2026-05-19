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
    
    $results = $scraper->search('The Witcher 3');
    $stores = [];
    foreach ($results as $r) {
        $stores[$r['store']] = $r['price_eur'];
    }
    
    return response()->json([
        'stores' => $stores,
        'count' => count($stores),
        'has_kinguin' => isset($stores['Kinguin']),
        'has_g2a' => isset($stores['G2A']),
        'has_gamivo' => isset($stores['Gamivo']),
        'has_gamesplanet' => isset($stores['Gamesplanet']),
    ]);
})->middleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);
