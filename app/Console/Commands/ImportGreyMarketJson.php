<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportGreyMarketJson extends Command
{
    protected $signature = 'prices:import-grey-market-json';

    protected $description = 'Import grey market prices from data/grey_market_prices.json';

    public function handle(): int
    {
        $path = base_path('data/grey_market_prices.json');

        if (! file_exists($path)) {
            $this->warn("Grey market JSON not found at {$path}");
            return self::SUCCESS;
        }

        $json = json_decode(file_get_contents($path), true);
        $results = $json['results'] ?? [];

        if (empty($results)) {
            $this->warn('No grey market prices found in JSON.');
            return self::SUCCESS;
        }

        $stores = [
            'instant-gaming' => Store::firstOrCreate(
                ['slug' => 'instant-gaming'],
                [
                    'name' => 'Instant Gaming',
                    'website' => 'https://www.instant-gaming.com',
                    'is_active' => true,
                ]
            ),
            'kinguin' => Store::firstOrCreate(
                ['slug' => 'kinguin'],
                [
                    'name' => 'Kinguin',
                    'website' => 'https://www.kinguin.net',
                    'is_active' => true,
                ]
            ),
        ];

        $updated = 0;
        $created = 0;

        foreach ($results as $result) {
            $storeSlug = $result['store'] ?? null;
            $title = $result['title'] ?? null;
            $price = $result['price_eur'] ?? null;
            $url = $result['url'] ?? null;

            if (! $storeSlug || ! $title || ! $price || ! isset($stores[$storeSlug])) {
                continue;
            }

            $store = $stores[$storeSlug];

            // Find matching game by title similarity
            $game = $this->findGame($title);

            if (! $game) {
                Log::debug("Grey market: no game match for '{$title}'");
                continue;
            }

            $attributes = [
                'current_price' => $price,
                'original_price' => $price * 1.3, // Estimate original price
                'discount_percent' => round((1 - 1 / 1.3) * 100),
                'is_real_price' => true,
                'url' => $url,
                'affiliate_url' => $this->buildAffiliateUrl($url, $storeSlug),
                'in_stock' => true,
                'currency' => 'EUR',
                'platform' => 'PC',
                'region' => 'global',
                'type' => 'key',
            ];

            $product = Product::where('game_id', $game->id)
                ->where('store_id', $store->id)
                ->first();

            if ($product) {
                $product->fill($attributes)->save();
                $updated++;
            } else {
                Product::create(array_merge($attributes, [
                    'game_id' => $game->id,
                    'store_id' => $store->id,
                ]));
                $created++;
            }
        }

        $this->info("Grey market import: created {$created}, updated {$updated} products.");

        Cache::flush();

        return self::SUCCESS;
    }

    private function findGame(string $title): ?Game
    {
        // Exact match
        $game = Game::whereRaw('LOWER(title) = LOWER(?)', [$title])->first();
        if ($game) {
            return $game;
        }

        // Contains match
        $game = Game::whereRaw('LOWER(title) LIKE LOWER(?)', ['%' . $title . '%'])->first();
        if ($game) {
            return $game;
        }

        // Reverse contains
        $game = Game::whereRaw('LOWER(?) LIKE LOWER(CONCAT(\'%\', title, \'%\'))', [$title])->first();

        return $game;
    }

    private function buildAffiliateUrl(string $url, string $storeSlug): string
    {
        $config = config("services.affiliates.{$storeSlug}");
        if (! $config || empty($config['id'])) {
            return $url;
        }

        $param = $config['param'] ?? 'aff';
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . $param . '=' . $config['id'];
    }
}
