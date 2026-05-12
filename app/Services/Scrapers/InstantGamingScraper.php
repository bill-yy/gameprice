<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstantGamingScraper
{
    public function search(string $query): ?array
    {
        try {
            $results = $this->searchInstantGaming($query);

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
            Log::warning('Instant Gaming scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function searchInstantGaming(string $query): array
    {
        $url = 'https://www.instant-gaming.com/en/search/?query=' . urlencode($query);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate',
            'Referer' => 'https://www.instant-gaming.com/',
        ])->timeout(30)->get($url);

        if (!$response->successful()) {
            Log::debug('Instant Gaming: HTTP ' . $response->status() . ' for query: ' . $query);
            return [];
        }

        $html = $response->body();

        $products = $this->extractFromSearchResults($html);

        if (!empty($products)) {
            return $products;
        }

        return $this->extractFromHtml($html, $query);
    }

    private function extractFromSearchResults(string $html): array
    {
        if (!preg_match('/window\.searchResults\s*=\s*(\{.*?\});\s*<\/script>/s', $html, $match)) {
            return [];
        }

        $data = json_decode($match[1], true);
        if (!is_array($data)) {
            return [];
        }

        $hits = $data['hits'] ?? [];
        if (!is_array($hits)) {
            return [];
        }

        $products = [];

        foreach ($hits as $item) {
            $name = $item['name'] ?? $item['fullname'] ?? null;
            if (!$name) {
                continue;
            }

            $price = $item['price'] ?? $item['price_eur'] ?? null;
            if ($price === null) {
                continue;
            }
            $price = (float) $price;

            $retail = $item['retail'] ?? $item['default_retail'] ?? null;
            $originalPrice = $retail !== null ? (float) $retail : $price;

            $discount = $item['discount'] ?? null;
            if ($discount === null && $originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }
            $discount = (int) ($discount ?? 0);

            $seoName = $item['seo_name'] ?? null;
            $url = $seoName ? "https://www.instant-gaming.com/en/{$seoName}/" : '';

            $hasStock = (int) ($item['has_stock'] ?? 1);

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $originalPrice,
                'discount_percent' => $discount,
                'url' => $url,
                'in_stock' => $hasStock === 1,
            ];
        }

        return $products;
    }

    private function extractFromHtml(string $html, string $query): array
    {
        $products = [];

        preg_match_all('/<a[^>]+href=["\'](?:https?:\/\/(?:www\.)?instant-gaming\.com)?\/en\/([^"\']+)\/["\'][^>]*class=["\'][^"\']*cover[^"\']*["\'][^>]*>(.*?)<\/a>/si', $html, $linkMatches, PREG_SET_ORDER);

        $seen = [];

        foreach ($linkMatches as $linkMatch) {
            $slug = trim($linkMatch[1], '/');
            $linkContent = $linkMatch[2];

            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            if (!preg_match('/<[^>]+class=["\'][^"\']*title[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/si', $linkContent, $nameMatch)) {
                if (!preg_match('/<[^>]+>([^<]{5,200})<\/[^>]+>/si', $linkContent, $nameMatch)) {
                    continue;
                }
            }
            $name = trim(strip_tags($nameMatch[1]));

            $price = null;
            if (preg_match('/([\d]+[.,][\d]{2})\s*(?:&nbsp;)?(?:€|EUR)/i', $linkContent, $priceMatch)) {
                $price = (float) str_replace(',', '.', $priceMatch[1]);
            }

            $originalPrice = null;
            if (preg_match('/<[^>]+class=["\'][^"\']*(?:retail|old|original|was)[^"\']*["\'][^>]*>.*?([\d]+[.,][\d]{2})/si', $linkContent, $origMatch)) {
                $originalPrice = (float) str_replace(',', '.', $origMatch[1]);
            }

            if (!$price || !$name) {
                continue;
            }

            $orig = $originalPrice ?? $price;
            $discount = 0;
            if ($orig > 0 && $orig > $price) {
                $discount = (int) round((1 - $price / $orig) * 100);
            }

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $orig,
                'discount_percent' => $discount,
                'url' => "https://www.instant-gaming.com/en/{$slug}/",
                'in_stock' => true,
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
