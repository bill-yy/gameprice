<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheapSharkScraper
{
    private const USD_TO_EUR = 0.92;

    private function usdToEur(float $usd): float
    {
        return round($usd * self::USD_TO_EUR, 2);
    }

    public function search(string $query): ?array
    {
        try {
            $result = $this->searchDeals($query);

            if ($result === null) {
                $result = $this->searchGames($query);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('CheapShark scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function searchDeals(string $query): ?array
    {
        $url = 'https://www.cheapshark.com/api/1.0/deals?title=' . urlencode($query) . '&pageSize=5&sortBy=Price';

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ])->timeout(10)->get($url);

        Log::info('Scraper cheapshark: result', [
            'game' => $query,
            'endpoint' => 'deals',
            'success' => $response->successful(),
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

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

        $dealId = $bestMatch['dealID'] ?? null;
        $dealUrl = $dealId ? "https://www.cheapshark.com/redirect?dealID={$dealId}" : '';

        return [
            'name' => $bestMatch['title'] ?? null,
            'price_eur' => $this->usdToEur($salePrice),
            'original_price_eur' => $this->usdToEur($normalPrice),
            'discount_percent' => $discount,
            'url' => $dealUrl,
            'in_stock' => $salePrice > 0,
        ];
    }

    private function searchGames(string $query): ?array
    {
        $url = 'https://www.cheapshark.com/api/1.0/games?title=' . urlencode($query) . '&limit=5';

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ])->timeout(10)->get($url);

        Log::info('Scraper cheapshark: result', [
            'game' => $query,
            'endpoint' => 'games',
            'success' => $response->successful(),
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            return null;
        }

        $games = $response->json();

        if (!is_array($games) || empty($games)) {
            return null;
        }

        $bestMatch = $this->findBestMatchFromGames($games, $query);

        if (!$bestMatch) {
            return null;
        }

        $cheapest = (float) ($bestMatch['cheapest'] ?? 0);
        $dealId = $bestMatch['cheapestDealID'] ?? null;
        $dealUrl = $dealId ? "https://www.cheapshark.com/redirect?dealID={$dealId}" : '';

        return [
            'name' => $bestMatch['external'] ?? null,
            'price_eur' => $this->usdToEur($cheapest),
            'original_price_eur' => $this->usdToEur($cheapest),
            'discount_percent' => 0,
            'url' => $dealUrl,
            'in_stock' => $cheapest > 0,
        ];
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

    private function findBestMatchFromGames(array $games, string $query): ?array
    {
        usort($games, function ($a, $b) use ($query) {
            $aScore = 0;
            $bScore = 0;

            similar_text(strtolower($a['external'] ?? ''), strtolower($query), $aSim);
            similar_text(strtolower($b['external'] ?? ''), strtolower($query), $bSim);
            $aScore += $aSim;
            $bScore += $bSim;

            if (stripos($a['external'] ?? '', $query) !== false) {
                $aScore += 20;
            }
            if (stripos($b['external'] ?? '', $query) !== false) {
                $bScore += 20;
            }

            return $bScore <=> $aScore;
        });

        return $games[0] ?? null;
    }
}
