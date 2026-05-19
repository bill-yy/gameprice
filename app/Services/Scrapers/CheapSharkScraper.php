<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheapSharkScraper
{
    private string $baseUrl = 'https://www.cheapshark.com/api/1.0';

    private array $storeMap = [
        '1' => 'steam',
        '2' => 'gamersgate',
        '3' => 'green-man-gaming',
        '7' => 'gog',
        '11' => 'humble-bundle',
        '13' => 'uplay',
        '15' => 'fanatical',
        '21' => 'wingamestore',
        '23' => 'gamebillet',
        '25' => 'epic-games-store',
        '27' => 'gamesplanet',
        '28' => 'gamesload',
        '29' => '2game',
        '30' => 'indiegala',
        '35' => 'dreamgame',
    ];

    /**
     * Search CheapShark for a game title.
     * Tries /deals first (has discount info), falls back to /games (works for all titles).
     *
     * @return array{name: string, price_eur: float, original_price_eur: float, discount_percent: int, url: string}|null
     */
    public function search(string $gameTitle): ?array
    {
        // Try deals endpoint first
        $result = $this->searchDeals($gameTitle);

        // Fallback to /games for AAA titles with no active deals
        if ($result === null) {
            $result = $this->searchGames($gameTitle);
        }

        return $result;
    }

    private function searchDeals(string $query): ?array
    {
        try {
            $url = $this->baseUrl . '/deals?title=' . urlencode($query) . '&pageSize=5&sortBy=Price';
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $deals = $response->json();
            if (empty($deals)) {
                return null;
            }

            $best = $this->findBestMatch($deals, $query);
            if (! $best) {
                return null;
            }

            return [
                'name' => $best['title'],
                'price_eur' => (float) $best['salePrice'],
                'original_price_eur' => (float) ($best['normalPrice'] ?? $best['salePrice']),
                'discount_percent' => (int) ($best['savings'] ?? 0),
                'url' => 'https://www.cheapshark.com/redirect?dealID=' . $best['dealID'],
                'store_id' => $best['storeID'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('CheapShark deals search error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function searchGames(string $query): ?array
    {
        try {
            $url = $this->baseUrl . '/games?title=' . urlencode($query) . '&limit=5';
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $games = $response->json();
            if (empty($games)) {
                return null;
            }

            $best = $this->findBestMatch($games, $query, 'external');
            if (! $best) {
                return null;
            }

            $price = (float) ($best['cheapest'] ?? 0);
            $dealId = $best['cheapestDealID'] ?? null;

            return [
                'name' => $best['external'],
                'price_eur' => $price,
                'original_price_eur' => $price,
                'discount_percent' => 0,
                'url' => $dealId ? 'https://www.cheapshark.com/redirect?dealID=' . $dealId : '',
                'store_id' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('CheapShark games search error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Find the best matching deal/game from results.
     */
    private function findBestMatch(array $items, string $query, string $titleField = 'title'): ?array
    {
        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            $title = $item[$titleField] ?? '';
            $score = $this->similarity($title, $query);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        // Require at least 50% similarity
        return $bestScore > 0.5 ? $best : null;
    }

    /**
     * Get store info from CheapShark.
     *
     * @return array<int, array{id: string, storeName: string, isActive: int, images: array}>
     */
    public function getStores(): array
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl . '/stores');

            return $response->successful() ? $response->json() : [];
        } catch (\Throwable $e) {
            Log::error('CheapShark stores error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Map CheapShark store ID to our store slug.
     */
    public function mapStoreId(?string $storeId): ?string
    {
        return $this->storeMap[$storeId] ?? null;
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
