<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XboxStoreScraper
{
    public static function getStoreName(): string
    {
        return 'Xbox Store';
    }

    public function search(string $query): ?array
    {
        try {
            $results = $this->searchXbox($query);

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
                'in_stock' => $bestMatch['in_stock'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Xbox Store scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function searchAll(string $query): array
    {
        try {
            $results = $this->searchXbox($query);

            return array_map(fn ($r) => [
                'store' => self::getStoreName(),
                'name' => $r['name'],
                'price' => $r['price_eur'],
                'original_price' => $r['original_price_eur'],
                'discount_percent' => $r['discount_percent'],
                'currency' => 'EUR',
                'url' => $r['url'],
                'in_stock' => $r['in_stock'],
                'platform' => $r['platform'] ?? 'Xbox Series X|S',
            ], $results);
        } catch (\Throwable $e) {
            Log::warning('Xbox Store scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function searchXbox(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
            'MS-CV' => bin2hex(random_bytes(16)),
        ])->timeout(10)->get('https://displaycatalog.mp.microsoft.com/v7.0/products', [
            'market' => 'ES',
            'languages' => 'en-US',
            'bigIds' => '',
            'actionFilter' => 'Browse',
            'query' => $query,
        ]);

        Log::info('Scraper xbox-store: result', [
            'game' => $query,
            'endpoint' => 'api',
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            return $this->searchXboxFallback($query);
        }

        $data = $response->json();

        return $this->parseAPIResults($data);
    }

    private function searchXboxFallback(string $query): array
    {
        $url = 'https://www.xbox.com/es-ES/games/store/search?q=' . urlencode($query);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(10)->get($url);

        Log::info('Scraper xbox-store: result', [
            'game' => $query,
            'endpoint' => 'fallback',
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
                $items = $data['props']['pageProps']['searchResults']['products']
                    ?? $data['props']['pageProps']['products']
                    ?? [];

                foreach ($items as $item) {
                    $name = $item['title'] ?? $item['name'] ?? null;
                    if (!$name) {
                        continue;
                    }

                    $price = $this->extractPrice($item);
                    if ($price === null) {
                        continue;
                    }

                    $productId = $item['id'] ?? ($item['productId'] ?? '');
                    $productUrl = $item['url'] ?? "https://www.xbox.com/es-ES/games/store/p/{$productId}";

                    $platform = $this->detectPlatform($item);

                    $products[] = [
                        'name' => $name,
                        'price_eur' => $price,
                        'original_price_eur' => $this->extractOriginalPrice($item, $price),
                        'discount_percent' => $this->calculateDiscount(
                            $this->extractOriginalPrice($item, $price),
                            $price
                        ),
                        'url' => $productUrl,
                        'platform' => $platform,
                        'in_stock' => ($item['availability'] ?? 'available') !== 'unavailable',
                    ];
                }
            }
        }

        return $products;
    }

    private function parseAPIResults(array $data): array
    {
        $products = [];

        $items = $data['Products']
            ?? $data['products']
            ?? $data['data']['products']
            ?? [];

        foreach ($items as $item) {
            $name = $item['ProductTitle']
                ?? $item['Title']
                ?? $item['Name']
                ?? $item['productTitle']
                ?? null;
            if (!$name) {
                continue;
            }

            $price = $this->extractPrice($item);
            if ($price === null) {
                continue;
            }

            $productId = $item['ProductId']
                ?? $item['Id']
                ?? $item['product-id']
                ?? '';
            $productUrl = "https://www.xbox.com/es-ES/games/store/p/{$productId}";

            $platform = $this->detectPlatform($item);

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $this->extractOriginalPrice($item, $price),
                'discount_percent' => $this->calculateDiscount(
                    $this->extractOriginalPrice($item, $price),
                    $price
                ),
                'url' => $productUrl,
                'platform' => $platform,
                'in_stock' => ($item['Availability'] ?? $item['availability'] ?? 'available') !== 'unavailable',
            ];
        }

        return $products;
    }

    private function extractPrice(array $item): ?float
    {
        $priceFields = [
            'DisplayPrice', 'Price', 'SalePrice',
            'ListPrice', 'MSRP', 'displayPrice',
            'price', 'salePrice',
        ];

        foreach ($priceFields as $field) {
            if (isset($item[$field])) {
                $val = $item[$field];
                if (is_array($val)) {
                    $val = $val['Value'] ?? $val['Amount'] ?? $val['value'] ?? $val['amount'] ?? null;
                }
                if ($val !== null && (float) $val > 0) {
                    return (float) $val;
                }
            }
        }

        $pricing = $item['DisplaySkuAvailabilities'][0]['Availabilities'][0]['OrderManagementData']['Price']
            ?? $item['Sku']['DisplaySkuAvailabilities'][0]['Availabilities'][0]['OrderManagementData']['Price']
            ?? null;

        if ($pricing) {
            $listPrice = $pricing['ListPrice'] ?? $pricing['MSRP'] ?? null;
            if ($listPrice !== null) {
                return (float) $listPrice;
            }
        }

        return null;
    }

    private function extractOriginalPrice(array $item, float $salePrice): float
    {
        $originalFields = ['OriginalPrice', 'ListPrice', 'MSRP', 'originalPrice', 'listPrice'];

        foreach ($originalFields as $field) {
            if (isset($item[$field])) {
                $val = $item[$field];
                if (is_array($val)) {
                    $val = $val['Value'] ?? $val['Amount'] ?? $val['value'] ?? $val['amount'] ?? null;
                }
                if ($val !== null && (float) $val > 0) {
                    return (float) $val;
                }
            }
        }

        $pricing = $item['DisplaySkuAvailabilities'][0]['Availabilities'][0]['OrderManagementData']['Price']
            ?? null;

        if ($pricing) {
            $msrp = $pricing['MSRP'] ?? $pricing['ListPrice'] ?? null;
            if ($msrp !== null && (float) $msrp > 0) {
                return (float) $msrp;
            }
        }

        return $salePrice;
    }

    private function calculateDiscount(float $original, float $sale): int
    {
        if ($original > 0 && $original > $sale) {
            return (int) round((1 - $sale / $original) * 100);
        }
        return 0;
    }

    private function detectPlatform(array $item): string
    {
        $categories = $item['Properties']['Category']
            ?? $item['Category']
            ?? $item['categories']
            ?? '';

        if (is_array($categories)) {
            $categories = implode(' ', array_map('strval', $categories));
        }

        $lower = strtolower($categories . ' ' . strtolower($item['ProductTitle'] ?? $item['Title'] ?? $item['title'] ?? ''));

        if (str_contains($lower, 'series x') || str_contains($lower, 'series s') || str_contains($lower, 'xbox series')) {
            return 'Xbox Series X|S';
        }
        if (str_contains($lower, 'xbox one')) {
            return 'Xbox One';
        }
        if (str_contains($lower, 'xbox 360')) {
            return 'Xbox 360';
        }

        return 'Xbox Series X|S';
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

            if ($a['platform'] === 'Xbox Series X|S') {
                $aScore += 3;
            }
            if ($b['platform'] === 'Xbox Series X|S') {
                $bScore += 3;
            }

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }
}
