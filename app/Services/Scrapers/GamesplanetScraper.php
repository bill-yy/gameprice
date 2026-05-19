<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;

class GamesplanetScraper
{
    private const BASE_URL = 'https://us.gamesplanet.com';
    private const USD_TO_EUR_RATE = 0.92;

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
        $response = ScraperProxy::get(self::BASE_URL . '/search', [
            'query' => $query,
        ], [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Referer' => self::BASE_URL . '/',
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

        return $this->parseHtmlProducts($response->body());
    }

    private function parseHtmlProducts(string $html): array
    {
        $products = [];

        // Match product rows: game_list game_list_small
        preg_match_all(
            '/<div[^>]*class="[^"]*game_list[^"]*"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>/s',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $block = $match[0];

            // Extract title from h4 > a or img alt
            $name = null;
            if (preg_match('/<h4[^>]*>.*?<a[^>]*>([^<]+)<\/a>/s', $block, $nameMatch)) {
                $name = trim($nameMatch[1]);
            } elseif (preg_match('/<img[^>]*alt="([^"]*)"/', $block, $imgMatch)) {
                $name = trim($imgMatch[1]);
            }

            if (! $name) {
                continue;
            }

            // Extract link
            $url = null;
            if (preg_match('/href="(\/game\/[^"]+)"/', $block, $linkMatch)) {
                $url = self::BASE_URL . $linkMatch[1];
            }

            // Extract current price
            $price = null;
            if (preg_match('/<span[^>]*class="[^"]*price_current[^"]*"[^>]*>([^<]+)<\/span>/', $block, $priceMatch)) {
                $priceStr = trim($priceMatch[1]);
                $price = $this->parsePrice($priceStr);
            }

            if ($price === null || $price <= 0) {
                continue;
            }

            // Extract original/base price if exists
            $originalPrice = null;
            if (preg_match('/<span[^>]*class="[^"]*price_base[^"]*"[^>]*>([^<]+)<\/span>/', $block, $baseMatch)) {
                $originalPrice = $this->parsePrice(trim($baseMatch[1]));
            }
            if ($originalPrice === null || $originalPrice <= 0) {
                $originalPrice = $price;
            }

            // Calculate discount
            $discount = 0;
            if ($originalPrice > $price && $originalPrice > 0) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }

            // Extract region info from URL or title
            $region = 'global';
            if (stripos($name, 'Steam') !== false) {
                $region = 'global'; // Steam keys are typically global on Gamesplanet
            }

            $products[] = [
                'name' => $name,
                'price_eur' => $this->usdToEur($price),
                'original_price_eur' => $this->usdToEur($originalPrice),
                'discount_percent' => $discount,
                'url' => $url ?? self::BASE_URL,
                'region' => $region,
                'in_stock' => true, // Gamesplanet shows available products
            ];
        }

        return $products;
    }

    private function parsePrice(string $priceStr): ?float
    {
        // Remove currency symbol and whitespace, handle commas
        $cleaned = preg_replace('/[^\d.,]/', '', $priceStr);
        $cleaned = str_replace(',', '', $cleaned); // Remove thousand separators
        
        $value = (float) $cleaned;
        return $value > 0 ? $value : null;
    }

    private function usdToEur(float $usd): float
    {
        return round($usd * self::USD_TO_EUR_RATE, 2);
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
