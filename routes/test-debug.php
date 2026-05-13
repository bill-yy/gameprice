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
    Log::info('testScraper: START', [
        'game_id' => $game->id,
        'game_title' => $game->title,
    ]);

    try {
        $scraper = new \App\Services\Scrapers\CheapSharkScraper();
        $result = $scraper->search($game->title);

        Log::info('testScraper: RESULT', [
            'game_id' => $game->id,
            'result' => $result,
        ]);

        return response()->json([
            'success' => true,
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'slug' => $game->slug,
            ],
            'scraper' => 'CheapShark',
            'result' => $result,
        ]);
    } catch (\Throwable $e) {
        Log::error('testScraper: FAILED', [
            'game_id' => $game->id,
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
            ],
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : null,
        ], 500);
    }
});
