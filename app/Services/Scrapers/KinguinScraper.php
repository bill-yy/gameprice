<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KinguinScraper
{
    public static function getStoreName(): string
    {
        return 'Kinguin';
    }

    public function searchAll(string $query): array
    {
        try {
            $results = $this->searchKinguin($query);

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
                'region' => $r['region'] ?? 'global',
            ], $results);
        } catch (\Throwable $e) {
            Log::warning('Kinguin scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function search(string $query): ?array
    {
        try {
            $searchResults = $this->searchKinguin($query);

            if (empty($searchResults)) {
                return null;
            }

            $bestMatch = $this->findBestMatch($searchResults, $query);

            if (!$bestMatch) {
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
            Log::warning('Kinguin scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function searchKinguin(string $query): array
    {
        $response = ScraperProxy::get('https://www.kinguin.net/svc/search/api/v1/products', [
            'q' => $query,
            'limit' => 24,
            'sort' => 'score',
        ], [
            'headers' => [
                'Accept' => 'application/json',
                'Referer' => 'https://www.kinguin.net/',
                'Origin' => 'https://www.kinguin.net',
            ],
            'timeout' => 30,
        ]);

        Log::info('Scraper kinguin: result', [
            'game' => $query,
            'success' => $response->successful(),
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();

        return $this->parseProducts($data, $query);
    }

    private function parseProducts(array $data, string $query): array
    {
        $products = [];

        $items = $data['data']['items']
            ?? $data['data']['products']
            ?? $data['items']
            ?? $data['products']
            ?? [];

        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            $name = $item['name'] ?? $item['title'] ?? null;
            if (!$name) {
                continue;
            }

            $price = $item['price'] ?? $item['currentPrice'] ?? $item['minPrice'] ?? null;
            if (is_array($price)) {
                $price = $price['amount'] ?? $price['value'] ?? $price['eur'] ?? null;
            }
            if ($price === null) {
                continue;
            }
            $price = (float) $price;

            $originalPrice = $item['originalPrice'] ?? $item['basePrice'] ?? $item['msrp'] ?? $item['marketPrice'] ?? null;
            if (is_array($originalPrice)) {
                $originalPrice = $originalPrice['amount'] ?? $originalPrice['value'] ?? $originalPrice['eur'] ?? null;
            }
            $originalPrice = $originalPrice !== null ? (float) $originalPrice : $price;

            $discount = $item['discount'] ?? $item['discountPercent'] ?? null;
            if ($discount === null && $originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }
            $discount = (int) ($discount ?? 0);

            $slug = $item['slug'] ?? $item['id'] ?? null;
            $url = $slug ? "https://www.kinguin.net/en/catalogsearch/result?q=" . urlencode($query) . "&p=1&mv=0&sort=default&manufacturers=&price_min=&price_max=&platforms=&tags=&release_date=&include=&exclude=&hide=out-of-stock" : ($item['url'] ?? "https://www.kinguin.net/en/catalogsearch/result?q=" . urlencode($query));

            if (isset($item['url']) && is_string($item['url'])) {
                $url = $item['url'];
            } elseif ($slug) {
                $url = "https://www.kinguin.net/en/product/{$slug}";
            }

            $region = 'global';
            if (isset($item['region']) && is_string($item['region'])) {
                $region = strtolower($item['region']);
            } elseif (isset($item['regions']) && is_array($item['regions'])) {
                $regionNames = array_map('strtolower', $item['regions']);
                if (in_array('global', $regionNames)) {
                    $region = 'global';
                } elseif (!empty($regionNames)) {
                    $region = $regionNames[0];
                }
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

            if ($a['region'] === 'global') {
                $aScore += 3;
            }
            if ($b['region'] === 'global') {
                $bScore += 3;
            }

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }
}
