<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Product;
use App\Services\Scrapers\CDKeysScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use App\Services\Scrapers\PSNStoreScraper;
use App\Services\Scrapers\XboxStoreScraper;
use Illuminate\Http\JsonResponse;

class DebugScraperController extends Controller
{
    private const SCRAPERS = [
        'cheapshark' => CheapSharkScraper::class,
        'eneba' => EnebaScraper::class,
        'instant-gaming' => InstantGamingScraper::class,
        'g2a' => G2AScraper::class,
        'kinguin' => KinguinScraper::class,
        'cdkeys' => CDKeysScraper::class,
        'psn-store' => PSNStoreScraper::class,
        'xbox-store' => XboxStoreScraper::class,
    ];

    public function diagnose(Game $game): JsonResponse
    {
        $results = [];
        $totalStart = microtime(true);

        foreach (self::SCRAPERS as $slug => $scraperClass) {
            $start = microtime(true);

            try {
                $scraper = new $scraperClass;
                $result = $scraper->search($game->title);
                $elapsed = round(microtime(true) - $start, 3);

                $results[$slug] = [
                    'success' => true,
                    'elapsed_seconds' => $elapsed,
                    'found' => $result !== null,
                    'data' => $result,
                ];
            } catch (\Throwable $e) {
                $elapsed = round(microtime(true) - $start, 3);

                $results[$slug] = [
                    'success' => false,
                    'elapsed_seconds' => $elapsed,
                    'found' => false,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ];
            }
        }

        $totalElapsed = round(microtime(true) - $totalStart, 3);

        $existingProducts = Product::where('game_id', $game->id)
            ->with('store:id,name,slug')
            ->get()
            ->map(fn ($p) => [
                'store' => $p->store->name ?? $p->store->slug,
                'platform' => $p->platform,
                'price' => $p->current_price,
                'url' => $p->url,
                'price_fetched_at' => $p->price_fetched_at?->toIso8601String(),
            ]);

        return response()->json([
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'slug' => $game->slug,
            ],
            'total_elapsed_seconds' => $totalElapsed,
            'scrapers' => $results,
            'existing_products_count' => $existingProducts->count(),
            'existing_products' => $existingProducts,
        ]);
    }
}
