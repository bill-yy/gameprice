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
        $slugs = $this->generateSlugs($query);
        
        // Phase 1: Try direct product URLs
        foreach ($slugs as $slug) {
            $url = self::BASE_URL . '/buy-' . $slug . '-cd-key-compare-prices/';
            $html = $this->fetchPage($url);
            
            if ($html) {
                $offers = $this->parseOffers($html, $query);
                if (!empty($offers)) {
                    return $offers;
                }
            }
        }
        
        // Phase 2: Fall back to AllKeyShop search page
        Log::info('AllKeyShop: Direct slugs failed, trying search', ['query' => $query]);
        
        $searchUrl = self::BASE_URL . '/search/' . urlencode(str_replace(' ', '-', strtolower($query))) . '/';
        $searchHtml = $this->fetchPage($searchUrl);
        
        if (!$searchHtml) {
            Log::info('AllKeyShop: Search page failed', ['query' => $query]);
            return [];
        }
        
        // Extract product links from search results
        preg_match_all('/href="(https?:\/\/www\.allkeyshop\.com\/blog\/buy-[^"]+)"/', $searchHtml, $matches);
        $links = array_unique($matches[1] ?? []);
        
        if (empty($links)) {
            Log::info('AllKeyShop: No product links found in search', ['query' => $query]);
            return [];
        }
        
        // Score links by relevance to query
        $scoredLinks = [];
        $queryWords = array_filter(explode(' ', strtolower(preg_replace('/[^a-z0-9\s]/', '', $query))));
        
        foreach ($links as $link) {
            $slug = '';
            if (preg_match('/buy-(.+)-cd-key-compare-prices/', $link, $m)) {
                $slug = str_replace('-', ' ', $m[1]);
            }
            
            $score = 0;
            foreach ($queryWords as $word) {
                if (strlen($word) > 2 && strpos($slug, $word) !== false) {
                    $score++;
                }
            }
            
            $scoredLinks[] = ['link' => $link, 'score' => $score, 'slug' => $slug];
        }
        
        // Sort by score descending
        usort($scoredLinks, fn ($a, $b) => $b['score'] <=> $a['score']);
        
        Log::info('AllKeyShop: Search results scored', [
            'query' => $query,
            'top_result' => $scoredLinks[0]['slug'] ?? 'none',
            'score' => $scoredLinks[0]['score'] ?? 0,
        ]);
        
        // Try top 3 results
        foreach (array_slice($scoredLinks, 0, 3) as $item) {
            $html = $this->fetchPage($item['link']);
            if ($html) {
                $offers = $this->parseOffers($html, $query);
                if (!empty($offers)) {
                    return $offers;
                }
            }
        }
        
        return [];
    }
    
    private function fetchPage(string $url): ?string
    {
        try {
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
            ])->timeout(15)->get($url);
            
            if ($response->successful() && strlen($response->body()) > 5000) {
                return $response->body();
            }
        } catch (\Throwable $e) {
            Log::debug('AllKeyShop fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
        
        return null;
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
        
        if ($basic) {
            $slugs[] = $basic;
        }
        
        // With "the-" prefix if not present
        if ($basic && strpos($basic, 'the-') !== 0) {
            $slugs[] = 'the-' . $basic;
        }
        
        // Without "the-" prefix if present
        if (strpos($basic, 'the-') === 0) {
            $slugs[] = substr($basic, 4);
        }
        
        // Common number variations
        $numberMap = [
            '1' => ['1', 'i', 'one'],
            '2' => ['2', 'ii', 'two'],
            '3' => ['3', 'iii', 'three'],
            '4' => ['4', 'iv', 'four'],
            '5' => ['5', 'v', 'five'],
            '6' => ['6', 'vi', 'six'],
            '7' => ['7', 'vii', 'seven'],
            '8' => ['8', 'viii', 'eight'],
            '9' => ['9', 'ix', 'nine'],
        ];
        
        // Try replacing numbers with Roman numerals and vice versa
        foreach ($numberMap as $num => $variants) {
            foreach ($variants as $from) {
                foreach ($variants as $to) {
                    if ($from !== $to && strpos($basic, '-' . $from . '-') !== false) {
                        $slugs[] = str_replace('-' . $from . '-', '-' . $to . '-', $basic);
                    }
                    if ($from !== $to && strpos($basic, '-' . $from) !== false) {
                        $slugs[] = str_replace('-' . $from, '-' . $to, $basic);
                    }
                }
            }
        }
        
        // Common abbreviations
        $replacements = [
            'grand-theft-auto' => 'gta',
            'call-of-duty' => 'cod',
            'counter-strike' => 'cs',
            'world-of-warcraft' => 'wow',
            'playerunknowns-battlegrounds' => 'pubg',
            'league-of-legends' => 'lol',
        ];
        
        foreach ($replacements as $full => $abbr) {
            if (strpos($basic, $full) !== false) {
                $slugs[] = str_replace($full, $abbr, $basic);
            }
            if (strpos($basic, $abbr) !== false) {
                $slugs[] = str_replace($abbr, $full, $basic);
            }
        }
        
        // Common suffixes
        $suffixes = ['', '-wild-hunt', '-game-of-the-year', '-goty', '-definitive', '-ultimate'];
        $baseGame = preg_replace('/-(\d+|i+|wild-hunt|game-of-the-year|goty|definitive|ultimate).*$/', '', $basic);
        
        if ($baseGame && $baseGame !== $basic) {
            foreach ($suffixes as $suffix) {
                $candidate = $baseGame . $suffix;
                if ($candidate !== $basic) {
                    $slugs[] = $candidate;
                }
            }
        }
        
        return array_values(array_unique(array_filter($slugs)));
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
            Log::debug('AllKeyShop: No valid Product JSON-LD found', ['query' => $query]);
            return [];
        }
        
        $offers = $productData['offers']['offers'];
        
        if (!is_array($offers)) {
            Log::debug('AllKeyShop: Offers is not an array', ['query' => $query]);
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
            'K4G' => 'K4G',
            'GAMESEAL' => 'GAMESEAL',
        ];
        
        return $mapping[$seller] ?? $seller;
    }
}
