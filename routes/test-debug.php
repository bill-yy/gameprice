<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/debug-health', function () {
    return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
});

Route::get('/debug-model/{game}', function (\App\Models\Game $game) {
    return response()->json(['game_id' => $game->id, 'title' => $game->title]);
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
