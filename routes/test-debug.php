<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/debug-health', function () {
    return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
});

Route::get('/debug-model/{game}', function (\App\Models\Game $game) {
    return response()->json(['game_id' => $game->id, 'title' => $game->title, 'slug' => $game->slug]);
});

Route::get('/debug-scraper/{game}', function (\App\Models\Game $game) {
    try {
        $scraper = new \App\Services\Scrapers\CheapSharkScraper();
        $result = $scraper->search($game->title);
        return response()->json(['ok' => true, 'result' => $result]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

Route::get('/debug-test-scraper/{game}', function (\App\Models\Game $game) {
    try {
        $scraper = new \App\Services\Scrapers\CheapSharkScraper();
        $result = $scraper->search($game->title);
        
        return response()->json([
            'success' => true,
            'game_id' => $game->id,
            'result' => $result,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        ], 500);
    }
});
