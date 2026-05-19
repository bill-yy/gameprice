<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PSNStoreScraper
{
    public static function getStoreName(): string
    {
        return 'PSN Store';
    }

    public function searchAll(string $query): array
    {
        try {
            $results = $this->searchPSN($query);

            return array_map(fn ($r) => [
                'store' => self::getStoreName(),
                'name' => $r['name'],
                'price' => $r['price_eur'],
                'original_price' => $r['original_price_eur'],
                'discount_percent' => $r['discount_percent'],
                'currency' => 'EUR',
                'url' => $r['url'],
                'in_stock' => $r['in_stock'],
                'platform' => $r['platform'] ?? 'PS5',
            ], $results);
        } catch (\Throwable $e) {
            Log::warning('PSN Store scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function search(string $query): ?array
    {
        try {
            $results = $this->searchPSN($query);

            if (empty($results)) {
                return null;
            }

            $bestMatch = $this->findBestMatch($results, $query);

            if (!$bestMatch) {
                return null;
            }

            return [
                'name' => $bestMatch['name'],
                'price_eur' => $bestMatch['price_eur'],
                'original_price_eur' => $bestMatch['original_price_eur'],
                'discount_percent' => $bestMatch['discount_percent'],
                'url' => $bestMatch['url'],
                'platform' => $bestMatch['platform'],
                'in_stock' => $bestMatch['in_stock'],
            ];
        } catch (\Throwable $e) {
            Log::warning('PSN Store scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function searchPSN(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(5)->get('https://web.np.playstation.com/api/catalog/search', [
            'store' => 'VP9BEPBGVRZC',
            'sort' => 'relevance',
            'game_content_type' => 'games',
            'size' => '10',
            'query' => $query,
        ]);

        Log::info('Scraper psn-store: result', [
            'game' => $query,
            'endpoint' => 'api',
            'success' => $response->successful(),
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            return $this->searchPSNFallback($query);
        }

        $data = $response->json();

        return $this->parseAPIResults($data);
    }

    private function searchPSNFallback(string $query): array
    {
        $url = 'https://store.playstation.com/es-es/search/' . urlencode($query);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(5)->get($url);

        Log::info('Scraper psn-store: result', [
            'game' => $query,
            'endpoint' => 'fallback',
            'success' => $response->successful(),
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            return [];
        }

        $html = $response->body();
        $products = [];

        if (preg_match('/__NEXT_DATA__.*?>(.*?)<\/script>/s', $html, $match)) {
            $data = json_decode($match[1], true);
            if ($data) {
                $apolloState = $data['props']['apolloState'] ?? [];
                $rootQuery = $apolloState['ROOT_QUERY'] ?? [];

                $searchResults = null;
                foreach ($rootQuery as $key => $value) {
                    if (str_contains($key, 'universalSearch')) {
                        $searchResults = $value;
                        break;
                    }
                }

                if ($searchResults && isset($searchResults['results'])) {
                    foreach ($searchResults['results'] as $ref) {
                        $productKey = $ref['__ref'] ?? null;
                        if (!$productKey || !isset($apolloState[$productKey])) {
                            continue;
                        }

                        $item = $apolloState[$productKey];
                        $name = $item['name'] ?? null;
                        if (!$name) {
                            continue;
                        }

                        $priceData = $item['price'] ?? null;
                        if (!$priceData) {
                            continue;
                        }

                        $basePrice = $this->parsePriceString($priceData['basePrice'] ?? null);
                        $discountedPrice = $this->parsePriceString($priceData['discountedPrice'] ?? null);

                        if ($discountedPrice === null) {
                            continue;
                        }

                        $sku = $item['id'] ?? '';
                        $platforms = $item['platforms'] ?? ['PS5'];
                        $platform = in_array('PS5', $platforms) ? 'PS5' : ($platforms[0] ?? 'PS5');

                        $products[] = [
                            'name' => $name,
                            'price_eur' => $discountedPrice,
                            'original_price_eur' => $basePrice ?? $discountedPrice,
                            'discount_percent' => $this->calculateDiscount($basePrice ?? $discountedPrice, $discountedPrice),
                            'url' => "https://store.playstation.com/es-es/product/{$sku}",
                            'platform' => $platform,
                            'in_stock' => true,
                        ];
                    }
                }
            }
        }

        return $products;
    }

    private function parsePriceString(?string $priceStr): ?float
    {
        if (!$priceStr) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9,]/', '', $priceStr);
        $cleaned = str_replace(',', '.', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function calculateDiscount(float $original, float $sale): int
    {
        if ($original > 0 && $original > $sale) {
            return (int) round((1 - $sale / $original) * 100);
        }
        return 0;
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

            if ($a['platform'] === 'PS5') {
                $aScore += 3;
            }
            if ($b['platform'] === 'PS5') {
                $bScore += 3;
            }

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }
}
