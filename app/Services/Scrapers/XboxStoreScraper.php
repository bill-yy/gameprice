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
                'platform' => $bestMatch['platform'],
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

    private function searchXbox(string $query): array
    {
        $url = 'https://www.xbox.com/en-us/search?q=' . urlencode($query);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(5)->get($url);

        Log::info('Scraper xbox-store: result', [
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
        $data = $this->extractPreloadedState($html);

        if (!$data) {
            return [];
        }

        $channelData = $data['core2']['channels']['channelData'] ?? [];
        $searchKey = null;
        foreach ($channelData as $key => $value) {
            if (str_contains($key, 'SEARCH_GAMES')) {
                $searchKey = $key;
                break;
            }
        }

        if (!$searchKey) {
            return [];
        }

        $productList = $channelData[$searchKey]['data']['products'] ?? [];
        $summaries = $data['core2']['products']['productSummaries'] ?? [];
        $availabilities = $data['core2']['products']['availabilitySummaries'] ?? [];

        foreach ($productList as $productRef) {
            $productId = $productRef['productId'] ?? '';
            if (!$productId || !isset($summaries[$productId])) {
                continue;
            }

            $summary = $summaries[$productId];
            $name = $summary['title'] ?? null;
            if (!$name) {
                continue;
            }

            $price = null;
            $originalPrice = null;
            $discountPercent = 0;

            $specificPrices = $summary['specificPrices']['purchaseable'] ?? [];
            if (!empty($specificPrices)) {
                $price = $specificPrices[0]['listPrice'] ?? null;
                $originalPrice = $specificPrices[0]['msrp'] ?? $price;
                $discountPercent = $specificPrices[0]['discountPercentage'] ?? 0;
            } elseif (isset($availabilities[$productId])) {
                $skuAvail = $availabilities[$productId];
                $firstSku = reset($skuAvail);
                $firstAvail = reset($firstSku);
                $price = $firstAvail['price']['listPrice'] ?? null;
                $originalPrice = $firstAvail['price']['msrp'] ?? $price;
                $discountPercent = $firstAvail['price']['discountPercentage'] ?? 0;
            }

            if ($price === null) {
                continue;
            }

            $platform = 'Xbox Series X|S';
            $availableOn = $summary['availableOn'] ?? [];
            if (in_array('XboxSeriesX', $availableOn) || in_array('XboxSeriesS', $availableOn)) {
                $platform = 'Xbox Series X|S';
            } elseif (in_array('XboxOne', $availableOn)) {
                $platform = 'Xbox One';
            } elseif (in_array('Xbox360', $availableOn)) {
                $platform = 'Xbox 360';
            }

            $products[] = [
                'name' => $name,
                'price_eur' => (float) $price,
                'original_price_eur' => (float) $originalPrice,
                'discount_percent' => (int) $discountPercent,
                'url' => "https://www.xbox.com/en-us/games/store/p/{$productId}",
                'platform' => $platform,
                'in_stock' => true,
            ];
        }

        return $products;
    }

    private function extractPreloadedState(string $html): ?array
    {
        if (!preg_match('/window\.__PRELOADED_STATE__\s*=\s*\{/s', $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]) - 1;
        $depth = 0;
        $end = $start;
        $length = strlen($html);

        for ($i = $start; $i < $length; $i++) {
            if ($html[$i] === '{') {
                $depth++;
            } elseif ($html[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        $jsonStr = substr($html, $start, $end - $start + 1);
        $data = json_decode($jsonStr, true);

        return is_array($data) ? $data : null;
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
