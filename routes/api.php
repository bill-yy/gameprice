<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StoreController;

Route::get('/health', fn () => response()->json(['status' => 'ok']))
    ->withoutMiddleware([ApiKeyMiddleware::class, RateLimitMiddleware::class]);

Route::prefix('v1')->group(function () {
    Route::get('/search', [SearchController::class, 'searchAll']);
    Route::get('/prices/{store}', [SearchController::class, 'searchByStore']);
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/deals', [SearchController::class, 'deals']);
});
