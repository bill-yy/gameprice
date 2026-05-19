<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IsThereAnyDeal API scraper for grey market prices.
 *
 * Requires ITAD_API_KEY env variable.
 * Register at: https://isthereanydeal.com/apps/my/
 *
 * Free tier: 3000 requests per 5 minutes (verified email).
 */
class IsThereAnyDealScraper
{
    private string $baseUrl = 'https://api.isthereanydeal.com';

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.itad.api_key') ?? env('ITAD_API_KEY');
    }

    /**
     * Check if the scraper is configured (has API key).
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Search for a game and get prices from all stores.
     *
     * @return array<int, array{store: string, store_slug: string, price_eur: float, original_price_eur: float|null, discount_percent: int, url: string, drm: string|null}>
     */
    public function search(string $gameTitle): array
    {
        if (! $this->isConfigured()) {
            Log::warning('IsThereAnyDeal scraper: no API key configured');

            return [];
        }

        try {
            // Step 1: Search for the game to get its ID
            $gameId = $this->lookupGame($gameTitle);
            if (! $gameId) {
                return [];
            }

            // Step 2: Get prices for the game
            return $this->getPrices($gameId);
        } catch (\Throwable $e) {
            Log::error('IsThereAnyDeal scraper exception', [
                'game' => $gameTitle,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Lookup game by title to get ITAD game ID.
     */
    private function lookupGame(string $title): ?string
    {
        $url = $this->baseUrl . '/games/search/v1';
        // ITAD requires key as query param, header ITAD-API-Key returns 403
        $response = Http::timeout(10)->get($url, [
            'key' => $this->apiKey,
            'title' => $title,
            'limit' => 5,
        ]);

        if (! $response->successful()) {
            Log::warning('ITAD lookup failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $games = $response->json();

        if (empty($games)) {
            return null;
        }

        // Find best match
        $best = null;
        $bestScore = 0;

        foreach ($games as $game) {
            $gameTitle = $game['title'] ?? '';
            $score = $this->similarity($gameTitle, $title);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $game;
            }
        }

        if ($bestScore < 0.5 || ! $best) {
            return null;
        }

        return $best['id'] ?? null;
    }

    /**
     * Get current prices for a game across all stores.
     *
     * @return array<int, array{store: string, store_slug: string, price_eur: float, original_price_eur: float|null, discount_percent: int, url: string, drm: string|null}>
     */
    private function getPrices(string $gameId): array
    {
        $url = $this->baseUrl . '/games/prices/v2';
        // ITAD prices v2 requires POST with JSON body
        $response = Http::timeout(10)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url . '?region=eu1&country=ES&key=' . $this->apiKey, [$gameId]);

        if (! $response->successful()) {
            Log::warning('ITAD prices failed', ['status' => $response->status(), 'body' => $response->body()]);

            return [];
        }

        $data = $response->json();
        $results = [];

        // Response is an array of objects with 'id' and 'deals'
        foreach ($data as $gameData) {
            $deals = $gameData['deals'] ?? [];

            foreach ($deals as $deal) {
                $price = $deal['price']['amount'] ?? null;
                if ($price === null) {
                    continue;
                }

                $regularPrice = $deal['regular']['amount'] ?? null;
                $cut = $deal['cut'] ?? 0;
                $storeName = $deal['shop']['name'] ?? 'Unknown';
                $storeSlug = $deal['shop']['slug'] ?? $this->slugify($storeName);
                $dealUrl = $deal['url'] ?? '';
                $drm = $deal['drm'] ?? [];
                $drmName = is_array($drm) && ! empty($drm) ? ($drm[0]['name'] ?? null) : null;

                $results[] = [
                    'store' => $storeName,
                    'store_slug' => $storeSlug,
                    'price_eur' => $price,
                    'original_price_eur' => $regularPrice,
                    'discount_percent' => $cut,
                    'url' => $dealUrl,
                    'drm' => $drmName,
                ];
            }
        }

        return $results;
    }

    /**
     * Get list of active shops/stores from ITAD.
     *
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    public function getShops(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/shops/v1', [
                'key' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $shops = $response->json();
            $results = [];

            foreach ($shops as $shop) {
                if ($shop['active'] ?? false) {
                    $results[] = [
                        'id' => $shop['id'],
                        'name' => $shop['name'],
                        'slug' => $shop['slug'],
                    ];
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::error('ITAD shops error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Convert store name to slug.
     */
    private function slugify(string $text): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }

    /**
     * Calculate string similarity.
     */
    private function similarity(string $a, string $b): float
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
