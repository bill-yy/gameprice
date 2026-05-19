<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\LandingController;
use App\Http\Controllers\Api\V1\Admin\ApiDashboardController;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => '1.0.1']))
    ->withoutMiddleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);

Route::get('/debug-allkeyshop', function () {
    $url = 'https://www.allkeyshop.com/blog/buy-the-witcher-3-wild-hunt-cd-key-compare-prices/';
    
    $response = Http::withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Referer' => 'https://www.google.com/',
    ])->timeout(20)->get($url);
    
    return response()->json([
        'status' => $response->status(),
        'body_length' => strlen($response->body()),
        'has_jsonld' => str_contains($response->body(), 'application/ld+json'),
        'has_offers' => str_contains($response->body(), '"offers"'),
        'first_500_chars' => substr($response->body(), 0, 500),
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
