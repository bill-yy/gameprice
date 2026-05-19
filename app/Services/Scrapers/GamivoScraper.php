<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;

class GamivoScraper
{
    public static function getStoreName(): string
    {
        return 'Gamivo';
    }

    public function searchAll(string $query): array
    {
        try {
            $results = $this->searchGamivo($query);

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
            Log::warning('Gamivo scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function search(string $query): ?array
    {
        try {
            $results = $this->searchGamivo($query);

            if (empty($results)) {
                return null;
            }

            $bestMatch = $this->findBestMatch($results, $query);

            if (! $bestMatch) {
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
            Log::warning('Gamivo scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function searchGamivo(string $query): array
    {
        $url = 'https://www.gamivo.com/search?q=' . urlencode($query);

        $response = ScraperProxy::get($url, [], [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Referer' => 'https://www.gamivo.com/',
            ],
            'timeout' => 30,
        ]);

        Log::info('Scraper gamivo: result', [
            'game' => $query,
            'success' => $response->successful(),
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (! $response->successful()) {
            return [];
        }

        $html = $response->body();

        $products = $this->extractFromJsonLd($html);

        if (! empty($products)) {
            return $products;
        }

        $products = $this->extractFromNextData($html);

        if (! empty($products)) {
            return $products;
        }

        return $this->extractFromHtml($html, $query);
    }

    private function extractFromJsonLd(string $html): array
    {
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $products = [];

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (! is_array($data)) {
                continue;
            }

            $items = $data;
            if (isset($data['@type']) && $data['@type'] === 'ItemList' && isset($data['itemListElement'])) {
                $items = $data['itemListElement'];
            }

            $items = is_array($items) && array_is_list($items) ? $items : [$items];

            foreach ($items as $item) {
                $product = $item;
                if (isset($item['item'])) {
                    $product = $item['item'];
                }

                if (($product['@type'] ?? null) !== 'Product') {
                    continue;
                }

                $name = $product['name'] ?? null;
                $offers = $product['offers'] ?? [];

                if (isset($offers['@type'])) {
                    $offers = [$offers];
                }

                $bestOffer = null;
                foreach ($offers as $offer) {
                    $price = $offer['price'] ?? null;
                    if ($price !== null && ($bestOffer === null || $price < $bestOffer)) {
                        $bestOffer = (float) $price;
                    }
                }

                if (! $name || $bestOffer === null) {
                    continue;
                }

                $url = $product['url'] ?? null;
                $originalPrice = null;
                $highPrice = $product['offers']['highPrice'] ?? $product['offers']['price'] ?? null;
                if ($highPrice !== null && (float) $highPrice > $bestOffer) {
                    $originalPrice = (float) $highPrice;
                }

                $discount = 0;
                if ($originalPrice && $originalPrice > 0) {
                    $discount = (int) round((1 - $bestOffer / $originalPrice) * 100);
                }

                $products[] = [
                    'name' => $name,
                    'price_eur' => $bestOffer,
                    'original_price_eur' => $originalPrice ?? $bestOffer,
                    'discount_percent' => $discount,
                    'url' => $url ?? '',
                    'in_stock' => true,
                ];
            }
        }

        return $products;
    }

    private function extractFromNextData(string $html): array
    {
        if (! preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/si', $html, $match)) {
            return [];
        }

        $data = json_decode($match[1], true);
        if (! is_array($data)) {
            return [];
        }

        $props = $data['props']['pageProps'] ?? [];

        $searchResults = $props['searchResults'] ?? $props['products'] ?? $props['items'] ?? [];

        if (isset($searchResults['hits'])) {
            $searchResults = $searchResults['hits'];
        }
        if (isset($searchResults['edges'])) {
            $searchResults = array_column($searchResults, 'node');
        }

        if (! is_array($searchResults) || ! array_is_list($searchResults)) {
            return [];
        }

        $products = [];

        foreach ($searchResults as $item) {
            $name = $item['name'] ?? $item['title'] ?? null;
            $price = $item['price'] ?? $item['currentPrice'] ?? $item['cheapestPrice'] ?? null;

            if (! $name || $price === null) {
                continue;
            }

            $price = (float) $price;
            $originalPrice = $item['originalPrice'] ?? $item['basePrice'] ?? $item['msrp'] ?? $price;
            $originalPrice = (float) $originalPrice;

            $discount = 0;
            if ($originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }

            $slug = $item['slug'] ?? $item['id'] ?? null;
            $url = $slug ? "https://www.gamivo.com/product/{$slug}" : ($item['url'] ?? '');

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $originalPrice,
                'discount_percent' => $discount,
                'url' => $url,
                'in_stock' => $item['inStock'] ?? $item['available'] ?? true,
            ];
        }

        return $products;
    }

    private function extractFromHtml(string $html, string $query): array
    {
        $products = [];

        preg_match_all('/<a[^>]+href=["\'](?:https?:\/\/(?:www\.)?gamivo\.com)?\/product\/([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $html, $linkMatches, PREG_SET_ORDER);

        $seen = [];

        foreach ($linkMatches as $linkMatch) {
            $slug = $linkMatch[1];
            $linkContent = $linkMatch[2];

            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            if (! preg_match('/<[^>]+class=["\'][^"\']*title[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/si', $linkContent, $nameMatch)) {
                if (! preg_match('/<[^>]+>([^<]{5,100})<\/[^>]+>/si', $linkContent, $nameMatch)) {
                    continue;
                }
            }
            $name = trim(strip_tags($nameMatch[1]));

            $price = null;
            $originalPrice = null;

            if (preg_match('/([\d]+[.,][\d]{2})\s*(?:€|EUR)/i', $linkContent, $priceMatch)) {
                $price = (float) str_replace(',', '.', $priceMatch[1]);
            }
            if (preg_match('/<[^>]+class=["\'][^"\']*(?:old|original|was|base)[^"\']*["\'][^>]*>.*?([\d]+[.,][\d]{2})/si', $linkContent, $origMatch)) {
                $originalPrice = (float) str_replace(',', '.', $origMatch[1]);
            }

            if (! $price || ! $name) {
                continue;
            }

            $discount = 0;
            $orig = $originalPrice ?? $price;
            if ($orig > 0 && $orig > $price) {
                $discount = (int) round((1 - $price / $orig) * 100);
            }

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $orig,
                'discount_percent' => $discount,
                'url' => "https://www.gamivo.com/product/{$slug}",
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
