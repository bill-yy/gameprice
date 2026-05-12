<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheapSharkScraper
{
    public function search(string $query): ?array
    {
        try {
            $url = 'https://www.cheapshark.com/api/1.0/deals?title=' . urlencode($query) . '&pageSize=5&sortBy=Price';

            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $deals = $response->json();

            if (!is_array($deals) || empty($deals)) {
                return null;
            }

            $bestMatch = $this->findBestMatch($deals, $query);

            if (!$bestMatch) {
                return null;
            }

            $normalPrice = (float) ($bestMatch['normalPrice'] ?? 0);
            $salePrice = (float) ($bestMatch['salePrice'] ?? 0);
            $discount = (int) ($bestMatch['savings'] ?? 0);

            $steamAppId = $bestMatch['steamAppID'] ?? null;
            $dealId = $bestMatch['dealID'] ?? null;
            $dealUrl = $dealId ? "https://www.cheapshark.com/redirect?dealID={$dealId}" : '';

            return [
                'name' => $bestMatch['title'] ?? null,
                'price_eur' => $salePrice,
                'original_price_eur' => $normalPrice,
                'discount_percent' => $discount,
                'url' => $dealUrl,
                'in_stock' => $salePrice > 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('CheapShark scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function findBestMatch(array $deals, string $query): ?array
    {
        usort($deals, function ($a, $b) use ($query) {
            $aScore = 0;
            $bScore = 0;

            similar_text(strtolower($a['title'] ?? ''), strtolower($query), $aSim);
            similar_text(strtolower($b['title'] ?? ''), strtolower($query), $bSim);
            $aScore += $aSim;
            $bScore += $bSim;

            if (stripos($a['title'] ?? '', $query) !== false) {
                $aScore += 20;
            }
            if (stripos($b['title'] ?? '', $query) !== false) {
                $bScore += 20;
            }

            $aRating = (float) ($a['steamRatingPercent'] ?? 0);
            $bRating = (float) ($b['steamRatingPercent'] ?? 0);
            $aScore += $aRating * 0.1;
            $bScore += $bRating * 0.1;

            return $bScore <=> $aScore;
        });

        return $deals[0] ?? null;
    }
}
