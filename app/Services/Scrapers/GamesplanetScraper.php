<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;

class GamesplanetScraper
{
    public static function getStoreName(): string
    {
        return 'Gamesplanet';
    }

    public function searchAll(string $query): array
    {
        try {
            $results = $this->searchGamesplanet($query);

            return array_map(fn ($r) => [
                'store' => self::getStoreName(),
                'name' => $r['name'],
                'price' => $r['price_eur'],
                'original_price' => $r['original_price_eur'],
                'discount_percent' => $r['discount_percent'],
                'currency' => 'EUR',
                'url' => $r['url'],
                'in_stock' => $r['in_stock'],
                'platform' => 'PC',
            ], $results);
        } catch (\Throwable $e) {
            Log::warning('Gamesplanet scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function search(string $query): ?array
    {
        try {
            $searchResults = $this->searchGamesplanet($query);

            if (empty($searchResults)) {
                return null;
            }

            $bestMatch = $this->findBestMatch($searchResults, $query);

            if (! $bestMatch) {
                return null;
            }

            return [
                'name' => $bestMatch['name'],
                'price_eur' => $bestMatch['price_eur'],
                'original_price_eur' => $bestMatch['original_price_eur'],
                'discount_percent' => $bestMatch['discount_percent'],
                'url' => $bestMatch['url'],
                'region' => $bestMatch['region'],
                'in_stock' => $bestMatch['in_stock'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Gamesplanet scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function searchGamesplanet(string $query): array
    {
        $response = ScraperProxy::get('https://us.gamesplanet.com/api/products/search', [
            'q' => $query,
        ], [
            'headers' => [
                'Accept' => 'application/json',
                'Referer' => 'https://us.gamesplanet.com/',
            ],
            'timeout' => 30,
        ]);

        Log::info('Scraper gamesplanet: result', [
            'game' => $query,
            'success' => $response->successful(),
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();

        return $this->parseProducts($data, $query);
    }

    private function parseProducts(array $data, string $query): array
    {
        $products = [];

        $items = $data['products']
            ?? $data['data']
            ?? $data['items']
            ?? $data['results']
            ?? [];

        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            $name = $item['name'] ?? $item['title'] ?? null;
            if (! $name) {
                continue;
            }

            $price = $item['price'] ?? $item['currentPrice'] ?? $item['final_price'] ?? null;
            if (is_array($price)) {
                $price = $price['amount'] ?? $price['value'] ?? $price['eur'] ?? null;
            }
            if ($price === null) {
                continue;
            }
            $price = (float) $price;

            $originalPrice = $item['originalPrice'] ?? $item['basePrice'] ?? $item['msrp'] ?? $item['regular_price'] ?? null;
            if (is_array($originalPrice)) {
                $originalPrice = $originalPrice['amount'] ?? $originalPrice['value'] ?? $originalPrice['eur'] ?? null;
            }
            $originalPrice = $originalPrice !== null ? (float) $originalPrice : $price;

            $discount = $item['discount'] ?? $item['discountPercent'] ?? $item['discount_percent'] ?? null;
            if ($discount === null && $originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }
            $discount = (int) ($discount ?? 0);

            $slug = $item['slug'] ?? $item['id'] ?? null;
            $url = $item['url'] ?? null;
            if (! $url && $slug) {
                $url = "https://us.gamesplanet.com/product/{$slug}";
            }
            if (! $url) {
                $url = "https://us.gamesplanet.com/search?query=" . urlencode($query);
            }

            $region = 'global';
            if (isset($item['region']) && is_string($item['region'])) {
                $region = strtolower($item['region']);
            } elseif (isset($item['territory']) && is_string($item['territory'])) {
                $region = strtolower($item['territory']);
            }

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $originalPrice,
                'discount_percent' => $discount,
                'url' => $url,
                'region' => $region,
                'in_stock' => $item['inStock'] ?? $item['available'] ?? $item['isAvailable'] ?? true,
            ];
        }

        return $products;
    }

    private function findBestMatch(array $results, string $gameTitle): ?array
    {
        usort($results, function ($a, $b) use ($gameTitle) {
            $aScore = 0;
            $bScore = 0;

            similar_text(strtolower($a['name']), strtolower($gameTitle), $aSim);
            similar_text(strtolower($b['name']), strtolower($gameTitle), $bSim);
            $aScore += $aSim;
            $bScore += $bSim;

            if (stripos($a['name'], $gameTitle) !== false) {
                $aScore += 20;
            }
            if (stripos($b['name'], $gameTitle) !== false) {
                $bScore += 20;
            }

            if (stripos($a['name'], 'Steam') !== false || stripos($a['name'], 'PC') !== false) {
                $aScore += 5;
            }
            if (stripos($b['name'], 'Steam') !== false || stripos($b['name'], 'PC') !== false) {
                $bScore += 5;
            }

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }
}
