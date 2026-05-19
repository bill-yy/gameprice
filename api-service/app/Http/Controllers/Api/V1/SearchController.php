<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Scrapers\CDKeysScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use App\Services\Scrapers\PSNStoreScraper;
use App\Services\Scrapers\XboxStoreScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    private const SCRAPERS = [
        'eneba' => EnebaScraper::class,
        'instant-gaming' => InstantGamingScraper::class,
        'cheapshark' => CheapSharkScraper::class,
        'g2a' => G2AScraper::class,
        'kinguin' => KinguinScraper::class,
        'cdkeys' => CDKeysScraper::class,
        'psn-store' => PSNStoreScraper::class,
        'xbox-store' => XboxStoreScraper::class,
    ];

    public function searchAll(Request $request): JsonResponse
    {
        $query = $request->input('q');

        if (!$query || trim($query) === '') {
            return response()->json([
                'success' => false,
                'error' => 'Missing required parameter: q',
            ], 400);
        }

        $query = trim($query);
        $allResults = [];
        $storesSearched = 0;

        foreach (self::SCRAPERS as $scraperClass) {
            try {
                $scraper = new $scraperClass();
                $results = $scraper->searchAll($query);
                $allResults = array_merge($allResults, $results);
                $storesSearched++;
            } catch (\Throwable $e) {
                Log::warning("Scraper {$scraperClass} failed", [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
                $storesSearched++;
            }
        }

        usort($allResults, fn ($a, $b) => ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX));

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $allResults,
            'meta' => [
                'count' => count($allResults),
                'stores_searched' => $storesSearched,
            ],
        ]);
    }

    public function searchByStore(Request $request, string $store): JsonResponse
    {
        $query = $request->input('q');

        if (!$query || trim($query) === '') {
            return response()->json([
                'success' => false,
                'error' => 'Missing required parameter: q',
            ], 400);
        }

        $store = strtolower(trim($store));

        if (!isset(self::SCRAPERS[$store])) {
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
                'meta' => ['count' => 0, 'stores_searched' => 1],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'store' => $store,
            'results' => $results,
            'meta' => [
                'count' => count($results),
                'stores_searched' => 1,
            ],
        ]);
    }

    public function deals(): JsonResponse
    {
        try {
            $cheapShark = new CheapSharkScraper();

            $response = \Illuminate\Support\Facades\Http::timeout(10)->get(
                'https://www.cheapshark.com/api/1.0/deals?storeID=1&upperPrice=15&pageSize=20&sortBy=Savings&onSale=1'
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to fetch deals',
                ], 500);
            }

            $deals = $response->json();

            $results = array_map(function ($deal) {
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
            }, is_array($deals) ? $deals : []);

            usort($results, fn ($a, $b) => $b['discount_percent'] <=> $a['discount_percent']);

            return response()->json([
                'success' => true,
                'results' => $results,
                'meta' => [
                    'count' => count($results),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Deals endpoint failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch deals',
            ], 500);
        }
    }
}
