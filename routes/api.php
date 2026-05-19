<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
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

// Temporary debug endpoint — REMOVE AFTER VERIFICATION
Route::post('/admin/run-scraper', function () {
    $source = request('source', 'itad');
    $limit = (int) request('limit', 5);
    
    \Illuminate\Support\Facades\Log::info('Manual scraper triggered via API', [
        'source' => $source,
        'limit' => $limit,
    ]);
    
    $exitCode = Artisan::call('prices:scrape-real', [
        '--source' => $source,
        '--limit' => $limit,
    ]);
    
    return response()->json([
        'success' => $exitCode === 0,
        'exit_code' => $exitCode,
        'output' => Artisan::output(),
    ]);
})->middleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);
