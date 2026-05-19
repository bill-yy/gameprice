<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnebaScraper
{
    private string $baseUrl = 'https://www.eneba.com';

    /**
     * Search Eneba for a game title and return the best match.
     *
     * @return array{name: string, price_eur: float, original_price_eur: float, discount_percent: int, url: string}|null
     */
    public function search(string $gameTitle): ?array
    {
        try {
            $searchUrl = $this->baseUrl . '/store/games?text=' . urlencode($gameTitle);

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->timeout(30)->get($searchUrl);

            if (! $response->successful()) {
                Log::warning('Eneba scraper HTTP error', ['status' => $response->status()]);

                return null;
            }

            $html = $response->body();

            return $this->extractBestMatch($html, $gameTitle);
        } catch (\Throwable $e) {
            Log::error('Eneba scraper exception', [
                'game' => $gameTitle,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract Apollo GraphQL JSON from HTML and find best matching product.
     */
    private function extractBestMatch(string $html, string $gameTitle): ?array
    {
        // Eneba embeds Apollo Client cache as JSON in <script> tags
        // Pattern: <script>window.__APOLLO_STATE__ = {...}</script>
        if (! preg_match('/window\.__APOLLO_STATE__\s*=\s*(\{.*?\});?<\/script>/s', $html, $matches)) {
            // Fallback: try to find any JSON with __typename:Product
            if (! preg_match_all('/<script[^>]*>(\{.*?\})<\/script>/s', $html, $scriptMatches)) {
                Log::warning('Eneba scraper: no Apollo state found');

                return null;
            }

            $data = null;
            foreach ($scriptMatches[1] as $script) {
                $script = $this->fixJson($script);
                $decoded = json_decode($script, true);
                if ($decoded && $this->hasProducts($decoded)) {
                    $data = $decoded;
                    break;
                }
            }

            if (! $data) {
                return null;
            }
        } else {
            $json = $this->fixJson($matches[1]);
            $data = json_decode($json, true);
        }

        if (! $data || ! is_array($data)) {
            return null;
        }

        $products = $this->extractProducts($data);
        if (empty($products)) {
            return null;
        }

        // Find best match by title similarity
        $bestMatch = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $score = $this->similarity($product['name'], $gameTitle);
            if ($score > $bestScore && $score > 0.5) {
                $bestScore = $score;
                $bestMatch = $product;
            }
        }

        return $bestMatch;
    }

    /**
     * Check if decoded data contains product entries.
     */
    private function hasProducts(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value) && isset($value['__typename']) && $value['__typename'] === 'Product') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract all products from Apollo cache data.
     *
     * @return array<int, array{name: string, price_eur: float, original_price_eur: float, discount_percent: int, url: string}>
     */
    private function extractProducts(array $data): array
    {
        $products = [];

        foreach ($data as $key => $value) {
            if (! is_array($value) || ! isset($value['__typename']) || $value['__typename'] !== 'Product') {
                continue;
            }

            $name = $value['name'] ?? null;
            $slug = $value['slug'] ?? null;

            if (! $name || ! $slug) {
                continue;
            }

            // Resolve cheapest auction
            $auction = $this->resolveAuction($value, $data);
            if (! $auction) {
                continue;
            }

            $priceCents = $this->resolveMoney($auction, 'price({"currency":"EUR"})', $data);
            if (! $priceCents) {
                continue;
            }

            $msrpCents = $this->resolveMoney($auction, 'msrp({"currency":"EUR"})', $data);
            $discount = $auction['msrpDiscountPercent'] ?? 0;

            $products[] = [
                'name' => $this->extractGameTitle($name),
                'price_eur' => $priceCents / 100,
                'original_price_eur' => ($msrpCents ?: $priceCents) / 100,
                'discount_percent' => $discount ?? 0,
                'url' => "{$this->baseUrl}/{$slug}",
            ];
        }

        return $products;
    }

    /**
     * Resolve auction reference from product.
     */
    private function resolveAuction(array $product, array $data): ?array
    {
        // Direct cheapestAuction
        if (isset($product['cheapestAuction']) && is_array($product['cheapestAuction'])) {
            if (isset($product['cheapestAuction']['__ref'])) {
                $ref = $product['cheapestAuction']['__ref'];

                return $data[$ref] ?? null;
            }

            return $product['cheapestAuction'];
        }

        return null;
    }

    /**
     * Resolve money amount from auction.
     */
    private function resolveMoney(?array $auction, string $field, array $data): ?int
    {
        if (! $auction || ! isset($auction[$field])) {
            return null;
        }

        $ref = $auction[$field];

        if (is_array($ref)) {
            if (isset($ref['__ref'])) {
                $money = $data[$ref['__ref']] ?? null;

                return $money['amount'] ?? null;
            }

            return $ref['amount'] ?? null;
        }

        return null;
    }

    /**
     * Remove platform/region suffixes from Eneba product names.
     */
    private function extractGameTitle(string $enebaName): string
    {
        // Remove "(PC) Steam Key GLOBAL", "Steam Key EUROPE", "Xbox Live Key", etc.
        $title = preg_replace(
            '/\s*\([^)]*\)\s*(Steam Key|Key|GOG Key|Xbox Live Key|PSN Key|Nintendo Switch Key)\s*(GLOBAL|EUROPE|US|ASIA|UNITED STATES|UNITED KINGDOM).*/i',
            '',
            $enebaName
        );

        return trim($title);
    }

    /**
     * Fix common JSON issues from inline scripts (unescaped quotes, trailing commas).
     */
    private function fixJson(string $json): string
    {
        // Remove trailing commas before } or ]
        $json = preg_replace('/,(\s*[}\]])/', '$1', $json);

        return $json;
    }

    /**
     * Calculate string similarity (0-1).
     */
    private function similarity(string $a, string $b): float
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
