<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnebaScraper
{
    public function search(string $query): ?array
    {
        try {
            $results = $this->searchEneba($query);

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
            Log::warning('Eneba scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function searchEneba(string $query): array
    {
        $url = 'https://www.eneba.com/store/games?text=' . urlencode($query);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(30)->get($url);

        if (!$response->successful()) {
            return [];
        }

        $html = $response->body();

        preg_match_all('/<script[^>]*>(\{.*?\})<\/script>/s', $html, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $products = [];

        foreach ($matches[1] as $scriptContent) {
            if (!str_contains($scriptContent, '__typename')) {
                continue;
            }

            $data = json_decode($scriptContent, true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $key => $value) {
                if (!is_array($value) || ($value['__typename'] ?? null) !== 'Product') {
                    continue;
                }

                $name = $value['name'] ?? null;
                $slug = $value['slug'] ?? null;
                $drm = $value['drm']['name'] ?? 'Unknown';
                $regions = array_column($value['regions'] ?? [], 'name');

                $cheapest = $value['cheapestAuction'] ?? null;
                if (!$cheapest || !is_array($cheapest)) {
                    continue;
                }

                $auctionRef = $cheapest['__ref'] ?? null;
                if (!$auctionRef || !isset($data[$auctionRef])) {
                    continue;
                }

                $auction = $data[$auctionRef];

                $priceCents = $this->resolveMoney($auction, 'price({"currency":"EUR"})', $data);
                $msrpCents = $this->resolveMoney($auction, 'msrp({"currency":"EUR"})', $data);
                $promoCents = $this->resolveMoney($auction, 'promotionalPrice({"currency":"EUR"})', $data);
                $discount = $auction['msrpDiscountPercent'] ?? $auction['promotionalDiscountPercent'] ?? null;

                if (!$priceCents) {
                    continue;
                }

                $products[] = [
                    'name' => $name,
                    'slug' => $slug,
                    'drm' => $drm,
                    'regions' => $regions,
                    'price_eur' => $priceCents / 100,
                    'original_price_eur' => ($msrpCents ?: $promoCents ?: $priceCents) / 100,
                    'discount_percent' => $discount,
                    'in_stock' => $auction['isInStock'] ?? false,
                    'url' => "https://www.eneba.com/{$slug}",
                ];
            }

            if (!empty($products)) {
                break;
            }
        }

        return $products;
    }

    private function resolveMoney(array $auction, string $key, array $data): ?int
    {
        $ref = $auction[$key] ?? null;

        if (is_array($ref) && isset($ref['__ref'])) {
            $money = $data[$ref['__ref']] ?? [];
            return $money['amount'] ?? null;
        }

        if (is_array($ref)) {
            return $ref['amount'] ?? null;
        }

        return null;
    }

    private function findBestMatch(array $results, string $gameTitle): ?array
    {
        $drmPriority = ['Steam' => 3, 'GOG.com' => 2, 'Unknown' => 1];
        $regionPriority = ['Global' => 3, 'Europe' => 2];

        usort($results, function ($a, $b) use ($drmPriority, $regionPriority, $gameTitle) {
            $aScore = 0;
            $bScore = 0;

            $aScore += $drmPriority[$a['drm']] ?? 0;
            $bScore += $drmPriority[$b['drm']] ?? 0;

            $aRegion = $a['regions'][0] ?? '';
            $bRegion = $b['regions'][0] ?? '';
            $aScore += $regionPriority[$aRegion] ?? 0;
            $bScore += $regionPriority[$bRegion] ?? 0;

            if (stripos($a['name'], $gameTitle) !== false) {
                $aScore += 5;
            }
            if (stripos($b['name'], $gameTitle) !== false) {
                $bScore += 5;
            }

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }
}
