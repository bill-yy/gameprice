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

        // Find all game links with their surrounding content
        // Each product has: <a href="/game/..."> with title, and nearby price_current
        preg_match_all(
            '/<a[^>]*href="(\/game\/[^"]+)"[^>]*>(?:\s*<img[^>]*alt="([^"]*)"[^>]*>)?/i',
            $html,
            $linkMatches,
            PREG_SET_ORDER
        );

        foreach ($linkMatches as $match) {
            $url = self::BASE_URL . $match[1];
            $name = isset($match[2]) && !empty($match[2]) ? trim($match[2]) : null;

            // If no alt text, try to find the title from nearby h4
            if (!$name) {
                $pos = strpos($html, $match[0]);
                if ($pos !== false) {
                    $nearby = substr($html, $pos, 500);
                    if (preg_match('/<h[1-6][^>]*>.*?<a[^>]*>([^<]+)<\/a>/s', $nearby, $titleMatch)) {
                        $name = trim($titleMatch[1]);
                    }
                }
            }

            if (!$name) {
                continue;
            }

            // Find price near this link
            $pos = strpos($html, $match[0]);
            $price = null;
            if ($pos !== false) {
                // Search in next 800 chars for price_current
                $nearby = substr($html, $pos, 800);
                if (preg_match('/<span[^>]*class="[^"]*price_current[^"]*"[^>]*>([^<]+)<\/span>/', $nearby, $priceMatch)) {
                    $price = $this->parsePrice(trim($priceMatch[1]));
                }
            }

            if ($price === null || $price <= 0) {
                continue;
            }

            // Check for original/base price
            $originalPrice = null;
            if ($pos !== false) {
                $nearby = substr($html, $pos, 800);
                if (preg_match('/<span[^>]*class="[^"]*price_base[^"]*"[^>]*>([^<]+)<\/span>/', $nearby, $baseMatch)) {
                    $originalPrice = $this->parsePrice(trim($baseMatch[1]));
                }
            }
            if ($originalPrice === null || $originalPrice <= 0) {
                $originalPrice = $price;
            }

            // Calculate discount
            $discount = 0;
            if ($originalPrice > $price && $originalPrice > 0) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }

            // Avoid duplicates (same URL)
            $existing = array_filter($products, fn($p) => $p['url'] === $url);
            if (!empty($existing)) {
                continue;
            }

            $products[] = [
                'name' => $name,
                'price_eur' => $this->usdToEur($price),
                'original_price_eur' => $this->usdToEur($originalPrice),
                'discount_percent' => $discount,
                'url' => $url,
                'region' => 'global',
                'in_stock' => true,
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
