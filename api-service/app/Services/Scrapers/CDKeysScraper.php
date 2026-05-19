<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CDKeysScraper
{
    public static function getStoreName(): string
    {
        return 'CDKeys';
    }

    public function search(string $query): ?array
    {
        try {
            $results = $this->searchCDKeys($query);

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
            Log::warning('CDKeys scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function searchAll(string $query): array
    {
        try {
            $results = $this->searchCDKeys($query);

            return array_map(fn ($r) => [
                'store' => self::getStoreName(),
                'name' => $r['name'],
                'price' => $r['price_eur'],
                'original_price' => $r['original_price_eur'],
                'discount_percent' => $r['discount_percent'],
                'currency' => 'EUR',
                'url' => $r['url'],
                'in_stock' => $r['in_stock'],
                'platform' => $r['platform'] ?? 'PC',
            ], $results);
        } catch (\Throwable $e) {
            Log::warning('CDKeys scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function searchCDKeys(string $query): array
    {
        $url = 'https://www.cdkeys.com/catalogsearch/result/?q=' . urlencode($query);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(10)->get($url);

        Log::info('Scraper cdkeys: result', [
            'game' => $query,
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            return [];
        }

        $html = $response->body();

        return $this->parseResults($html, $query);
    }

    private function parseResults(string $html, string $query): array
    {
        $products = [];

        preg_match_all(
            '/<li[^>]*class="item product product-item"[^>]*>(.*?)<\/li>/s',
            $html,
            $itemBlocks
        );

        if (empty($itemBlocks[1])) {
            preg_match_all(
                '/<div[^>]*class="product-item-info"[^>]*>(.*?)<\/div>\s*<\/div>/s',
                $html,
                $itemBlocks
            );
        }

        if (empty($itemBlocks[1])) {
            preg_match_all(
                '/<div[^>]*data-container="product-grid"[^>]*>(.*?)<\/div>/s',
                $html,
                $itemBlocks
            );
        }

        foreach ($itemBlocks[1] as $block) {
            $name = null;
            if (preg_match('/<a[^>]*class="product-item-link"[^>]*>(.*?)<\/a>/s', $block, $m)) {
                $name = trim(strip_tags($m[1]));
            }
            if (!$name && preg_match('/<img[^>]*alt="([^"]*)"/s', $block, $m)) {
                $name = trim($m[1]);
            }
            if (!$name) {
                continue;
            }

            $productUrl = null;
            if (preg_match('/<a[^>]*href="([^"]*)"[^>]*class="product-item-link"/s', $block, $m)) {
                $productUrl = trim($m[1]);
            }
            if (!$productUrl && preg_match('/<a[^>]*class="product-item-link"[^>]*href="([^"]*)"/s', $block, $m)) {
                $productUrl = trim($m[1]);
            }

            $price = null;
            if (preg_match('/class="price"[^>]*>[^\d]*([\d.,]+)/s', $block, $m)) {
                $price = (float) str_replace(',', '', $m[1]);
            }
            if (!$price && preg_match('/data-price-amount="([\d.,]+)"/s', $block, $m)) {
                $price = (float) str_replace(',', '', $m[1]);
            }

            if (!$price) {
                continue;
            }

            $originalPrice = $price;
            if (preg_match('/class="old-price"[^>]*>.*?class="price"[^>]*>[^\d]*([\d.,]+)/s', $block, $m)) {
                $originalPrice = (float) str_replace(',', '', $m[1]);
            }
            if (preg_match('/class="special-price".*?class="price"[^>]*>[^\d]*([\d.,]+)/s', $block, $m)) {
                $price = (float) str_replace(',', '', $m[1]);
            }

            $discount = 0;
            if ($originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }

            $platform = 'PC';
            $lowerName = strtolower($name);
            if (str_contains($lowerName, 'ps5') || str_contains($lowerName, 'playstation 5')) {
                $platform = 'PS5';
            } elseif (str_contains($lowerName, 'ps4') || str_contains($lowerName, 'playstation 4')) {
                $platform = 'PS4';
            } elseif (str_contains($lowerName, 'xbox series') || str_contains($lowerName, 'series x') || str_contains($lowerName, 'series s')) {
                $platform = 'Xbox Series X|S';
            } elseif (str_contains($lowerName, 'xbox one')) {
                $platform = 'Xbox One';
            } elseif (str_contains($lowerName, 'nintendo') || str_contains($lowerName, 'switch')) {
                $platform = 'Nintendo Switch';
            }

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $originalPrice,
                'discount_percent' => $discount,
                'url' => $productUrl ?? "https://www.cdkeys.com/catalogsearch/result/?q=" . urlencode($query),
                'platform' => $platform,
                'in_stock' => !str_contains(strtolower($block), 'out of stock'),
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

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }
}
