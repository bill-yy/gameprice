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
        ])->timeout(10)->get('https://web.np.playstation.com/api/catalog/search', [
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
        ])->timeout(10)->get($url);

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
                $items = $data['props']['pageProps']['searchResults']['included'] ?? [];
                foreach ($items as $item) {
                    $type = $item['type'] ?? '';
                    if ($type !== 'game') {
                        continue;
                    }

                    $name = $item['attributes']['name'] ?? null;
                    if (!$name) {
                        continue;
                    }

                    $price = $this->extractPriceFromAttributes($item['attributes'] ?? []);
                    if ($price === null) {
                        continue;
                    }

                    $sku = $item['id'] ?? '';
                    $platforms = $item['attributes']['platforms'] ?? ['PS5'];
                    $platform = in_array('PS5', $platforms) ? 'PS5' : ($platforms[0] ?? 'PS5');

                    $products[] = [
                        'name' => $name,
                        'price_eur' => $price,
                        'original_price_eur' => $this->extractOriginalPriceFromAttributes($item['attributes'] ?? [], $price),
                        'discount_percent' => $this->calculateDiscount(
                            $this->extractOriginalPriceFromAttributes($item['attributes'] ?? [], $price),
                            $price
                        ),
                        'url' => "https://store.playstation.com/es-es/product/{$sku}",
                        'platform' => $platform,
                        'in_stock' => true,
                    ];
                }
            }
        }

        return $products;
    }

    private function parseAPIResults(array $data): array
    {
        $products = [];

        $links = $data['data']['attributes']['search-results']['links'] ?? [];

        if (empty($links)) {
            $links = $data['included'] ?? [];
        }

        foreach ($links as $item) {
            $name = $item['attributes']['name']
                ?? $item['title']
                ?? null;
            if (!$name) {
                continue;
            }

            $price = $this->extractPriceFromAttributes($item['attributes'] ?? []);
            if ($price === null) {
                continue;
            }

            $originalPrice = $this->extractOriginalPriceFromAttributes($item['attributes'] ?? [], $price);
            $sku = $item['id'] ?? ($item['attributes']['product-id'] ?? '');

            $platforms = $item['attributes']['platforms'] ?? [];
            $platform = in_array('PS5', $platforms) ? 'PS5' : ($platforms[0] ?? 'PS5');

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $originalPrice,
                'discount_percent' => $this->calculateDiscount($originalPrice, $price),
                'url' => "https://store.playstation.com/es-es/product/{$sku}",
                'platform' => $platform,
                'in_stock' => ($item['attributes']['is-purchasable'] ?? true) === true,
            ];
        }

        return $products;
    }

    private function extractPriceFromAttributes(array $attrs): ?float
    {
        $price = $attrs['price']
            ?? $attrs['sale-price']
            ?? $attrs['current-price']
            ?? $attrs['skus'][0]['prices']['sale-price']
            ?? $attrs['skus'][0]['prices']['plus-price']
            ?? null;

        if ($price === null) {
            $bp = $attrs['bucket-price'] ?? null;
            if (is_array($bp)) {
                $price = $bp['sale-price'] ?? $bp['base-price'] ?? null;
            }
        }

        if ($price === null) {
            return null;
        }

        if (is_array($price)) {
            $price = $price['value'] ?? $price['amount'] ?? null;
        }

        return $price !== null ? (float) $price : null;
    }

    private function extractOriginalPriceFromAttributes(array $attrs, float $salePrice): float
    {
        $original = $attrs['original-price']
            ?? $attrs['base-price']
            ?? $attrs['list-price']
            ?? $attrs['skus'][0]['prices']['base-price']
            ?? null;

        if ($original === null) {
            $bp = $attrs['bucket-price'] ?? null;
            if (is_array($bp)) {
                $original = $bp['base-price'] ?? $bp['original-price'] ?? null;
            }
        }

        if ($original === null) {
            return $salePrice;
        }

        if (is_array($original)) {
            $original = $original['value'] ?? $original['amount'] ?? null;
        }

        return $original !== null ? (float) $original : $salePrice;
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
