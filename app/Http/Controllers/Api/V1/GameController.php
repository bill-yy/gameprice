<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Game;
use App\Services\Scrapers\AllKeyShopScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\GamesplanetScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\PSNStoreScraper;
use App\Services\Scrapers\XboxStoreScraper;
use App\Services\ScraperMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Game::query()->where('is_active', true);

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$search}%"]);
            });
        }

        $perPage = min($request->input('per_page', 20), 100);
        $games = $query->orderBy('title')->paginate($perPage);

        return response()->json([
            'success' => true,
            'games' => $games->items(),
            'meta' => [
                'total' => $games->total(),
                'per_page' => $games->perPage(),
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->orWhere('id', $slug)->first();

        if (! $game) {
            return response()->json([
                'success' => false,
                'error' => 'Game not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'game' => $game,
        ]);
    }

    public function prices(string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->orWhere('id', $slug)->first();

        if (! $game) {
            return response()->json([
                'success' => false,
                'error' => 'Game not found',
            ], 404);
        }

        $prices = $game->products()
            ->with('store')
            ->where('in_stock', true)
            ->where('current_price', '>', 0)
            ->orderBy('current_price')
            ->get()
            ->map(function ($product) {
                return [
                    'store' => $product->store?->name ?? 'Unknown',
                    'name' => $product->edition ?? 'Standard',
                    'price' => (float) $product->current_price,
                    'original_price' => (float) $product->original_price,
                    'discount_percent' => $product->discount_percent,
                    'currency' => $product->currency,
                    'url' => $product->url,
                    'in_stock' => $product->in_stock,
                    'platform' => $product->platform,
                    'updated_at' => $product->updated_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'slug' => $game->slug,
            ],
            'prices' => $prices,
            'meta' => [
                'count' => $prices->count(),
                'cached' => false,
            ],
        ]);
    }

    public function refreshPrices(string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->orWhere('id', $slug)->first();

        if (! $game) {
            return response()->json([
                'success' => false,
                'error' => 'Game not found',
            ], 404);
        }

        $cacheKey = "api.game.{$game->id}.prices.fresh";

        $result = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($game) {
            $allResults = [];
            $storesSearched = 0;
            $query = $game->title;

            $scrapers = [
                'eneba' => EnebaScraper::class,
                'instant-gaming' => InstantGamingScraper::class,
                'cheapshark' => CheapSharkScraper::class,
                'gamesplanet' => GamesplanetScraper::class,
                'allkeyshop' => AllKeyShopScraper::class,
                'psn-store' => PSNStoreScraper::class,
                'xbox-store' => XboxStoreScraper::class,
            ];

            foreach ($scrapers as $storeKey => $scraperClass) {
                try {
                    $scraper = new $scraperClass();
                    $results = $scraper->searchAll($query);

                    // Filter irrelevant using word boundaries on the exact title
                    $significantWords = array_filter(
                        explode(' ', strtolower(preg_replace('/[^a-z0-9\s]/', '', $query))),
                        fn($w) => strlen($w) >= 3
                    );
                    
                    if (!empty($significantWords)) {
                        $requiredMatches = max(1, ceil(count($significantWords) / 2));
                        $results = array_filter($results, function ($r) use ($significantWords, $requiredMatches) {
                            $name = strtolower($r['name'] ?? '');
                            $matchCount = 0;
                            foreach ($significantWords as $word) {
                                if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $name)) {
                                    $matchCount++;
                                }
                            }
                            return $matchCount >= $requiredMatches;
                        });
                    }

                    $allResults = array_merge($allResults, array_values($results));
                    $storesSearched++;
                    ScraperMonitor::recordSuccess($storeKey, count($results));
                } catch (\Throwable $e) {
                    Log::warning("Scraper {$scraperClass} failed", [
                        'game' => $game->title,
                        'error' => $e->getMessage(),
                    ]);
                    ScraperMonitor::recordFailure($storeKey, $e->getMessage());
                    $storesSearched++;
                }
            }

            // Filter price > 0
            $allResults = array_filter($allResults, fn ($r) => ($r['price'] ?? 0) > 0);
            usort($allResults, fn ($a, $b) => ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX));

            return [
                'results' => array_values($allResults),
                'storesSearched' => $storesSearched,
            ];
        });

        return response()->json([
            'success' => true,
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'slug' => $game->slug,
            ],
            'prices' => $result['results'],
            'meta' => [
                'count' => count($result['results']),
                'stores_searched' => $result['storesSearched'],
                'cached' => ! Cache::missing($cacheKey),
            ],
        ]);
    }
}
