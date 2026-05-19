<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeEneba extends Command
{
    protected $signature = 'prices:scrape-eneba {--limit=50 : Max games to process}';

    protected $description = 'Scrape real prices from Eneba using their Apollo GraphQL data';

    public function handle(): int
    {
        $store = Store::firstOrCreate(
            ['slug' => 'eneba'],
            [
                'name' => 'Eneba',
                'website' => 'https://www.eneba.com',
                'logo_url' => null,
                'is_active' => true,
                'affiliate_id' => config('services.affiliates.eneba.id'),
                'affiliate_param' => config('services.affiliates.eneba.param'),
            ]
        );

        $games = Game::where('is_active', true)
            ->whereNotNull('title')
            ->orderByDesc('metacritic_score')
            ->limit($this->option('limit'))
            ->get();

        if ($games->isEmpty()) {
            $this->warn('No games found.');
            return self::SUCCESS;
        }

        $updated = 0;
        $created = 0;
        $bar = $this->output->createProgressBar($games->count());

        foreach ($games as $game) {
            $bar->advance();

            try {
                $results = $this->searchEneba($game->title);

                if (empty($results)) {
                    continue;
                }

                // Find best matching PC/Steam/Global result
                $bestMatch = $this->findBestMatch($results, $game->title);

                if (! $bestMatch) {
                    continue;
                }

                $attributes = [
                    'current_price' => $bestMatch['price_eur'],
                    'original_price' => $bestMatch['original_price_eur'],
                    'discount_percent' => $bestMatch['discount_percent'],
                    'is_real_price' => true,
                    'url' => $bestMatch['url'],
                    'affiliate_url' => $this->buildAffiliateUrl($bestMatch['url']),
                    'in_stock' => $bestMatch['in_stock'],
                    'currency' => 'EUR',
                    'platform' => 'PC',
                    'region' => strtolower(implode(',', $bestMatch['regions'])),
                    'type' => 'key',
                ];

                Product::updateOrCreate(
                    ['game_id' => $game->id, 'store_id' => $store->id],
                    $attributes
                );

                if (Product::where('game_id', $game->id)->where('store_id', $store->id)->exists()) {
                    $updated++;
                } else {
                    $created++;
                }
            } catch (\Throwable $e) {
                Log::warning('Eneba scrape failed', [
                    'game' => $game->title,
                    'error' => $e->getMessage(),
                ]);
            }

            // Rate limiting
            usleep(500000); // 0.5s between requests
        }

        $bar->finish();
        $this->newLine();
        $this->info("Eneba: created {$created}, updated {$updated} products.");

        Cache::flush();

        return self::SUCCESS;
    }

    private function searchEneba(string $query): array
    {
        $url = 'https://www.eneba.com/store/games?text=' . urlencode($query);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->timeout(30)->get($url);

        if (! $response->successful()) {
            return [];
        }

        $html = $response->body();

        // Extract Apollo GraphQL JSON from script tags
        preg_match_all('/<script[^>]*>(\{.*?\})<\/script>/s', $html, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $products = [];

        foreach ($matches[1] as $scriptContent) {
            if (! str_contains($scriptContent, '__typename')) {
                continue;
            }

            $data = json_decode($scriptContent, true);
            if (! is_array($data)) {
                continue;
            }

            foreach ($data as $key => $value) {
                if (! is_array($value) || ($value['__typename'] ?? null) !== 'Product') {
                    continue;
                }

                $name = $value['name'] ?? null;
                $slug = $value['slug'] ?? null;
                $drm = $value['drm']['name'] ?? 'Unknown';
                $regions = array_column($value['regions'] ?? [], 'name');

                $cheapest = $value['cheapestAuction'] ?? null;
                if (! $cheapest || ! is_array($cheapest)) {
                    continue;
                }

                $auctionRef = $cheapest['__ref'] ?? null;
                if (! $auctionRef || ! isset($data[$auctionRef])) {
                    continue;
                }

                $auction = $data[$auctionRef];

                $priceCents = $this->resolveMoney($auction, 'price({"currency":"EUR"})', $data);
                $msrpCents = $this->resolveMoney($auction, 'msrp({"currency":"EUR"})', $data);
                $promoCents = $this->resolveMoney($auction, 'promotionalPrice({"currency":"EUR"})', $data);
                $discount = $auction['msrpDiscountPercent'] ?? $auction['promotionalDiscountPercent'] ?? null;

                if (! $priceCents) {
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

            // Stop after first valid script with data
            if (! empty($products)) {
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
        // Prefer Steam > GOG > PC > Global
        $drmPriority = ['Steam' => 3, 'GOG.com' => 2, 'Unknown' => 1];
        $regionPriority = ['Global' => 3, 'Europe' => 2];

        usort($results, function ($a, $b) use ($drmPriority, $regionPriority, $gameTitle) {
            $aScore = 0;
            $bScore = 0;

            // DRM priority
            $aScore += $drmPriority[$a['drm']] ?? 0;
            $bScore += $drmPriority[$b['drm']] ?? 0;

            // Region priority
            $aRegion = $a['regions'][0] ?? '';
            $bRegion = $b['regions'][0] ?? '';
            $aScore += $regionPriority[$aRegion] ?? 0;
            $bScore += $regionPriority[$bRegion] ?? 0;

            // Title similarity (exact match gets bonus)
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

    private function buildAffiliateUrl(string $url): string
    {
        $affId = config('services.affiliates.eneba.id');
        if ($affId) {
            $sep = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . 'af_id=' . $affId;
        }

        return $url;
    }
}
