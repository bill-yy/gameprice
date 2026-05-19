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
        $query = trim($query);
        
        // Phase 1: Try direct product URLs with generated slugs
        $slugs = $this->generateSlugs($query);
        
        foreach ($slugs as $slug) {
            $url = self::BASE_URL . '/buy-' . $slug . '-cd-key-compare-prices/';
            $html = $this->fetchPage($url);
            
            if ($html) {
                $offers = $this->parseOffers($html, $query);
                if (!empty($offers)) {
                    Log::info('AllKeyShop: Found via direct slug', [
                        'query' => $query,
                        'slug' => $slug,
                        'results' => count($offers),
                    ]);
                    return $offers;
                }
            }
        }
        
        // Phase 2: Fall back to AllKeyShop search page
        Log::info('AllKeyShop: Direct slugs failed, trying search', ['query' => $query]);
        
        $searchHtml = $this->fetchSearchPage($query);
        
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
        $scoredLinks = $this->scoreLinks($links, $query);
        
        Log::info('AllKeyShop: Search results scored', [
            'query' => $query,
            'links_found' => count($links),
            'top_result' => $scoredLinks[0]['slug'] ?? 'none',
            'score' => $scoredLinks[0]['score'] ?? 0,
        ]);
        
        // Try top 5 results (increased from 3)
        foreach (array_slice($scoredLinks, 0, 5) as $item) {
            $html = $this->fetchPage($item['link']);
            if ($html) {
                $offers = $this->parseOffers($html, $query);
                if (!empty($offers)) {
                    Log::info('AllKeyShop: Found via search fallback', [
                        'query' => $query,
                        'slug' => $item['slug'],
                        'results' => count($offers),
                    ]);
                    return $offers;
                }
            }
        }
        
        Log::info('AllKeyShop: No results from search fallback', [
            'query' => $query,
            'tried_links' => count($scoredLinks),
        ]);
        
        return [];
    }
    
    /**
     * Fetch the AllKeyShop search page for a query
     */
    private function fetchSearchPage(string $query): ?string
    {
        // Build search URL - AllKeyShop uses /search/{slug}/
        $searchSlug = $this->slugify($query);
        $searchUrl = self::BASE_URL . '/search/' . $searchSlug . '/';
        
        return $this->fetchPage($searchUrl);
    }
    
    /**
     * Score search result links by relevance to query
     */
    private function scoreLinks(array $links, string $query): array
    {
        $scoredLinks = [];
        $queryWords = array_filter(explode(' ', strtolower(preg_replace('/[^a-z0-9\s]/', '', $query))));
        
        foreach ($links as $link) {
            $slug = '';
            if (preg_match('/buy-(.+)-cd-key-compare-prices/', $link, $m)) {
                $slug = str_replace('-', ' ', $m[1]);
            }
            
            $score = 0;
            $slugLower = strtolower($slug);
            
            foreach ($queryWords as $word) {
                if (strlen($word) <= 2) continue;
                
                // Exact word match in slug
                if (strpos($slugLower, $word) !== false) {
                    $score += 10;
                }
                
                // Partial match
                similar_text($word, $slugLower, $percent);
                if ($percent > 70) {
                    $score += 5;
                }
            }
            
            // Bonus for exact query match
            if (strpos($slugLower, strtolower($query)) !== false) {
                $score += 50;
            }
            
            // Penalize unrelated words in slug that aren't in query
            $slugWords = array_filter(explode(' ', $slugLower));
            foreach ($slugWords as $word) {
                if (strlen($word) > 3 && !in_array($word, $queryWords)) {
                    $score -= 1; // Small penalty for extra words
                }
            }
            
            $scoredLinks[] = [
                'link' => $link,
                'score' => max(0, $score),
                'slug' => $slug,
            ];
        }
        
        // Sort by score descending
        usort($scoredLinks, fn ($a, $b) => $b['score'] <=> $a['score']);
        
        return $scoredLinks;
    }
    
    /**
     * Fetch a page from AllKeyShop
     */
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
            
            Log::debug('AllKeyShop fetch non-success', [
                'url' => $url,
                'status' => $response->status(),
                'length' => strlen($response->body()),
            ]);
        } catch (\Throwable $e) {
            Log::debug('AllKeyShop fetch exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
        
        return null;
    }
    
    /**
     * Generate multiple slug variants for a query
     */
    private function generateSlugs(string $query): array
    {
        $slugs = [];
        
        $basic = $this->slugify($query);
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
        
        // Common number variations (Roman ↔ Arabic)
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
        
        foreach ($numberMap as $num => $variants) {
            foreach ($variants as $from) {
                foreach ($variants as $to) {
                    if ($from !== $to) {
                        // Replace surrounded by dashes
                        if (strpos($basic, '-' . $from . '-') !== false) {
                            $slugs[] = str_replace('-' . $from . '-', '-' . $to . '-', $basic);
                        }
                        // Replace at end
                        if (substr($basic, -strlen('-' . $from)) === '-' . $from) {
                            $slugs[] = substr($basic, 0, -strlen($from)) . $to;
                        }
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
        
        // Common suffixes for games with numbers
        $suffixes = ['', '-wild-hunt', '-game-of-the-year', '-goty', '-definitive', '-ultimate', '-complete', '-deluxe'];
        $baseGame = preg_replace('/-(\d+|i+|wild-hunt|game-of-the-year|goty|definitive|ultimate|complete|deluxe|enhanced|remastered).*$/', '', $basic);
        
        if ($baseGame && $baseGame !== $basic) {
            foreach ($suffixes as $suffix) {
                $candidate = $baseGame . $suffix;
                if ($candidate !== $basic && !in_array($candidate, $slugs)) {
                    $slugs[] = $candidate;
                }
            }
        }
        
        return array_values(array_unique(array_filter($slugs)));
    }
    
    /**
     * Convert a string to a URL slug
     */
    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * Parse offers from AllKeyShop product page HTML
     */
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
    
    /**
     * Map AllKeyShop seller names to standard store names
     */
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
            'Gamesplanet US' => 'Gamesplanet',
            'Gamesplanet UK' => 'Gamesplanet',
            'Gamesplanet FR' => 'Gamesplanet',
            'CDKeys' => 'CDKeys',
            'Humble Store' => 'Humble Store',
            'Steam' => 'Steam',
            'GOG.com' => 'GOG',
            'Gog.com' => 'GOG',
            'K4G' => 'K4G',
            'GAMESEAL' => 'GAMESEAL',
            'Driffle' => 'Driffle',
            '2Game' => '2Game',
            '2Game EU' => '2Game',
            'Voidu' => 'Voidu',
            'Fanatical' => 'Fanatical',
            'Green Man Gaming' => 'Green Man Gaming',
            'DLGamer' => 'DLGamer',
            'GamersGate' => 'GamersGate',
            'Noctre' => 'Noctre',
            'Dreamgame' => 'Dreamgame',
            'HRK Game' => 'HRK Game',
            'Yuplay' => 'Yuplay',
            'Play-Asia' => 'Play-Asia',
            'JoyBuggy' => 'JoyBuggy',
            'Eneba EU' => 'Eneba',
            'Eneba US' => 'Eneba',
            'Eneba UK' => 'Eneba',
        ];
        
        return $mapping[$seller] ?? $seller;
    }
}
