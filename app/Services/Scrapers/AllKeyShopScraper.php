<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AllKeyShopScraper
{
    private const BASE_URL = 'https://www.allkeyshop.com/blog';

    public static function getStoreName(): string
    {
        return 'AllKeyShop';
    }

    public function searchAll(string $query): array
    {
        try {
            $results = $this->searchAllKeyShop($query);

            return array_map(fn ($r) => [
                'store' => $r['store'],
                'name' => $r['name'],
                'price' => $r['price'],
                'original_price' => $r['original_price'],
                'discount_percent' => $r['discount_percent'],
                'currency' => $r['currency'],
                'url' => $r['url'],
                'in_stock' => $r['in_stock'],
                'platform' => $r['platform'],
            ], $results);
        } catch (\Throwable $e) {
            Log::warning('AllKeyShop scraper failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function searchAllKeyShop(string $query): array
    {
        // Try to find the product page by constructing URL variants
        $slugs = $this->generateSlugs($query);
        
        $html = null;
        $foundUrl = null;
        
        foreach ($slugs as $slug) {
            $url = self::BASE_URL . '/buy-' . $slug . '-cd-key-compare-prices/';
            
            // Use direct HTTP instead of worker proxy (AllKeyShop blocks Cloudflare Workers)
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => 'https://www.google.com/',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'cross-site',
                'Cache-Control' => 'max-age=0',
            ])->timeout(20)->get($url);
            
            if ($response->successful() && strlen($response->body()) > 10000) {
                $html = $response->body();
                $foundUrl = $url;
                break;
            }
        }
        
        if (!$html) {
            Log::info('AllKeyShop: No product page found', ['query' => $query, 'tried_slugs' => $slugs]);
            return [];
        }
        
        Log::info('AllKeyShop: Product page found', [
            'query' => $query,
            'url' => $foundUrl,
        ]);
        
        return $this->parseOffers($html, $query);
    }
    
    private function generateSlugs(string $query): array
    {
        $slugs = [];
        
        // Basic slugification
        $basic = strtolower(trim($query));
        $basic = preg_replace('/[^a-z0-9\s-]/', '', $basic);
        $basic = preg_replace('/\s+/', '-', $basic);
        $basic = preg_replace('/-+/', '-', $basic);
        $basic = trim($basic, '-');
        
        $slugs[] = $basic;
        
        // Without "the"
        if (strpos($basic, 'the-') === 0) {
            $slugs[] = substr($basic, 4);
        }
        
        // Common variations
        $replacements = [
            '-iii' => '-3',
            '-ii' => '-2',
            '-iv' => '-4',
            '-v' => '-5',
            'grand-theft-auto' => 'gta',
            'call-of-duty' => 'cod',
            'assassins-creed' => 'assassins-creed',
        ];
        
        foreach ($replacements as $search => $replace) {
            if (strpos($basic, $search) !== false) {
                $slugs[] = str_replace($search, $replace, $basic);
            }
        }
        
        // GTA special case
        if (strpos($basic, 'gta-') === 0) {
            $slugs[] = str_replace('gta-', 'grand-theft-auto-', $basic);
        }
        
        return array_unique($slugs);
    }
    
    private function parseOffers(string $html, string $query): array
    {
        $products = [];
        
        // Extract full JSON-LD Product block
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        
        $productData = null;
        foreach ($matches[1] ?? [] as $jsonStr) {
            $jsonStr = trim($jsonStr);
            $data = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && ($data['@type'] ?? '') === 'Product') {
                $productData = $data;
                break;
            }
        }
        
        if (!$productData || !isset($productData['offers']['offers'])) {
            Log::warning('AllKeyShop: No valid Product JSON-LD found', ['query' => $query]);
            return [];
        }
        
        $offers = $productData['offers']['offers'];
        
        if (!is_array($offers)) {
            Log::warning('AllKeyShop: Offers is not an array', ['query' => $query]);
            return [];
        }
        
        // Extract game name from the page
        $gameName = $query;
        preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $titleMatch);
        if ($titleMatch) {
            $gameName = strip_tags($titleMatch[1]);
            $gameName = preg_replace('/\s*CD Key.*$/i', '', $gameName);
            $gameName = preg_replace('/\s*Compare Prices.*$/i', '', $gameName);
            $gameName = trim($gameName);
        }
        
        // Extract the lowest price for each merchant
        $merchantPrices = [];
        
        foreach ($offers as $offer) {
            $seller = $offer['seller']['name'] ?? 'Unknown';
            $price = isset($offer['price']) ? (float) $offer['price'] : null;
            $currency = $offer['priceCurrency'] ?? 'EUR';
            $availability = $offer['availability'] ?? '';
            
            if ($price === null || $price <= 0) {
                continue;
            }
            
            $inStock = strpos($availability, 'InStock') !== false;
            
            // Only keep the lowest price per merchant
            if (!isset($merchantPrices[$seller]) || $merchantPrices[$seller]['price'] > $price) {
                $merchantPrices[$seller] = [
                    'price' => $price,
                    'currency' => $currency,
                    'in_stock' => $inStock,
                ];
            }
        }
        
        // Convert to product format
        foreach ($merchantPrices as $seller => $data) {
            // Map store names to standard names
            $storeName = $this->mapStoreName($seller);
            
            $products[] = [
                'store' => $storeName,
                'name' => $gameName . ' (' . $seller . ')',
                'price' => $data['price'],
                'original_price' => $data['price'],
                'discount_percent' => 0,
                'currency' => $data['currency'],
                'url' => 'https://www.allkeyshop.com/blog/catalogue/search.php?q=' . urlencode($query),
                'in_stock' => $data['in_stock'],
                'platform' => 'PC',
            ];
        }
        
        // Sort by price
        usort($products, fn ($a, $b) => $a['price'] <=> $b['price']);
        
        return $products;
    }
    
    private function mapStoreName(string $seller): string
    {
        $mapping = [
            'Kinguin' => 'Kinguin',
            'G2A' => 'G2A',
            'GAMIVO' => 'Gamivo',
            'Gamivo' => 'Gamivo',
            'Eneba' => 'Eneba',
            'Instant Gaming' => 'Instant Gaming',
            'Gamesplanet' => 'Gamesplanet',
            'CDKeys' => 'CDKeys',
            'Humble Store' => 'Humble Store',
            'Steam' => 'Steam',
            'GOG.com' => 'GOG',
            'Gog.com' => 'GOG',
        ];
        
        return $mapping[$seller] ?? $seller;
    }
}
