<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Scrapers\AllKeyShopScraper;
use App\Services\Scrapers\CDKeysScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\GamivoScraper;
use App\Services\Scrapers\GamesplanetScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use App\Services\Scrapers\PSNStoreScraper;
use App\Services\Scrapers\XboxStoreScraper;
use App\Services\ScraperMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    private const SCRAPERS = [
        'eneba' => EnebaScraper::class,
        'instant-gaming' => InstantGamingScraper::class,
        'cheapshark' => CheapSharkScraper::class,
        'g2a' => G2AScraper::class,
        'kinguin' => KinguinScraper::class,
        'gamivo' => GamivoScraper::class,
        'gamesplanet' => GamesplanetScraper::class,
        'allkeyshop' => AllKeyShopScraper::class,
        'cdkeys' => CDKeysScraper::class,
        'psn-store' => PSNStoreScraper::class,
        'xbox-store' => XboxStoreScraper::class,
    ];

    public function searchAll(Request $request): JsonResponse
    {
        $query = $request->input('q');

        if (! $query || trim($query) === '') {
            return response()->json([
                'success' => false,
                'error' => 'Missing required parameter: q',
            ], 400);
        }

        $query = trim($query);
        $cacheKey = 'api.search.' . md5(strtolower($query));

        $result = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($query) {
            $allResults = [];
            $storesSearched = 0;

            foreach (self::SCRAPERS as $storeKey => $scraperClass) {
                try {
                    $scraper = new $scraperClass();
                    $results = $scraper->searchAll($query);
                    $allResults = array_merge($allResults, $results);
                    $storesSearched++;
                    ScraperMonitor::recordSuccess($storeKey, count($results));
                } catch (\Throwable $e) {
                    Log::warning("Scraper {$scraperClass} failed", [
                        'query' => $query,
                        'error' => $e->getMessage(),
                    ]);
                    ScraperMonitor::recordFailure($storeKey, $e->getMessage());
                    $storesSearched++;
                }
            }

            // Filtrar productos con precio 0 o inválido
            $allResults = array_filter($allResults, fn ($r) => ($r['price'] ?? 0) > 0);

            usort($allResults, fn ($a, $b) => ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX));

            // Get suggestions if very few results
            $suggestions = [];
            if (count($allResults) < 3) {
                try {
                    $aksScraper = new AllKeyShopScraper();
                    $suggestions = $aksScraper->getSuggestions($query);
                } catch (\Throwable $e) {
                    Log::warning('Failed to get search suggestions', [
                        'query' => $query,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'allResults' => array_values($allResults),
                'storesSearched' => $storesSearched,
                'suggestions' => $suggestions,
            ];
        });

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $result['allResults'],
            'suggestions' => $result['suggestions'] ?? [],
            'meta' => [
                'count' => count($result['allResults']),
                'stores_searched' => $result['storesSearched'],
                'cached' => ! Cache::missing($cacheKey),
            ],
        ]);
    }

    public function searchByStore(Request $request, string $store): JsonResponse
    {
        $query = $request->input('q');

        if (! $query || trim($query) === '') {
            return response()->json([
                'success' => false,
                'error' => 'Missing required parameter: q',
            ], 400);
        }

        $store = strtolower(trim($store));

        if (! isset(self::SCRAPERS[$store])) {
            return response()->json([
                'success' => false,
                'error' => 'Unknown store. Available stores: ' . implode(', ', array_keys(self::SCRAPERS)),
            ], 404);
        }

        $query = trim($query);
        $scraperClass = self::SCRAPERS[$store];

        try {
            $scraper = new $scraperClass();
            $results = $scraper->searchAll($query);
        } catch (\Throwable $e) {
            Log::warning("Scraper {$scraperClass} failed", [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'query' => $query,
                'error' => 'Scraper failed: ' . $e->getMessage(),
                'results' => [],
                'suggestions' => [],
                'meta' => ['count' => 0, 'stores_searched' => 1],
            ], 500);
        }

        // Filtrar precios 0
        $results = array_filter($results, fn ($r) => ($r['price'] ?? 0) > 0);

        // Get suggestions if very few results
        $suggestions = [];
        if (count($results) < 3) {
            try {
                $aksScraper = new AllKeyShopScraper();
                $suggestions = $aksScraper->getSuggestions($query);
            } catch (\Throwable $e) {
                Log::warning('Failed to get search suggestions', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'store' => $store,
            'results' => array_values($results),
            'suggestions' => $suggestions,
            'meta' => [
                'count' => count($results),
                'stores_searched' => 1,
            ],
        ]);
    }

    public function deals(): JsonResponse
    {
        $cacheKey = 'api.deals.v2';
        $cached = Cache::get($cacheKey);

        try {
            $results = Cache::remember($cacheKey, now()->addMinutes(15), function () {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                ])->timeout(15)->connectTimeout(10)->get(
                    'https://www.cheapshark.com/api/1.0/deals?storeID=1&upperPrice=15&pageSize=20&sortBy=Savings&onSale=1'
                );

                if (! $response->successful()) {
                    Log::warning('CheapShark deals API returned non-success', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    // Return empty to trigger fallback below
                    return [];
                }

                $deals = $response->json();

                if (! is_array($deals)) {
                    return [];
                }

                $mapped = array_map(function ($deal) {
                    $salePrice = (float) ($deal['salePrice'] ?? 0);
                    $normalPrice = (float) ($deal['normalPrice'] ?? 0);
                    $dealId = $deal['dealID'] ?? null;

                    return [
                        'store' => 'CheapShark',
                        'name' => $deal['title'] ?? '',
                        'price' => $salePrice,
                        'original_price' => $normalPrice,
                        'discount_percent' => (int) ($deal['savings'] ?? 0),
                        'currency' => 'USD',
                        'url' => $dealId ? "https://www.cheapshark.com/redirect?dealID={$dealId}" : '',
                        'platform' => 'PC',
                        'steam_rating' => (float) ($deal['steamRatingPercent'] ?? 0),
                    ];
                }, $deals);

                // Filtrar precios 0
                $mapped = array_filter($mapped, fn ($r) => ($r['price'] ?? 0) > 0);

                usort($mapped, fn ($a, $b) => $b['discount_percent'] <=> $a['discount_percent']);

                return array_values($mapped);
            });
        } catch (\Throwable $e) {
            Log::warning('Deals endpoint exception', ['error' => $e->getMessage()]);

            // Devolver cache anterior si existe, sino array vacío con success true
            if ($cached) {
                return response()->json([
                    'success' => true,
                    'results' => $cached,
                    'meta' => [
                        'count' => count($cached),
                        'from_cache' => true,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'results' => [],
                'meta' => [
                    'count' => 0,
                    'error' => 'Unable to fetch fresh deals',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'meta' => [
                'count' => count($results),
                'cached' => ! Cache::missing($cacheKey),
            ],
        ]);
    }
}
