<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportGamivoJson extends Command
{
    protected $signature = 'gamivo:import-json';

    protected $description = 'Import Gamivo prices from data/gamivo_prices.json';

    public function handle(): int
    {
        $path = base_path('data/gamivo_prices.json');

        if (!file_exists($path)) {
            $this->warn("Gamivo JSON not found at {$path}");
            return self::SUCCESS;
        }

        $items = json_decode(file_get_contents($path), true);

        if (empty($items)) {
            $this->warn('No Gamivo prices found in JSON.');
            return self::SUCCESS;
        }

        $store = Store::firstOrCreate(
            ['slug' => 'gamivo'],
            [
                'name' => 'Gamivo',
                'website' => 'https://www.gamivo.com',
                'logo_url' => 'https://www.gamivo.com/favicon.ico',
                'is_active' => true,
                'is_official' => false,
            ]
        );

        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $name = $item['name'] ?? null;
            $gameTitle = $item['game_title'] ?? null;
            $price = $item['price_eur'] ?? null;
            $originalPrice = $item['original_price_eur'] ?? $price;
            $discount = $item['discount_percent'] ?? null;
            $url = $item['url'] ?? null;
            $inStock = $item['in_stock'] ?? true;

            if (!$name || !$price) {
                continue;
            }

            $cleanTitle = $this->extractGameTitle($name);
            $region = $this->extractRegion($name);
            $game = $this->findGame($gameTitle ?? $cleanTitle);

            if (!$game) {
                Log::debug("Gamivo import: no game match for '{$cleanTitle}' (from '{$name}')");
                continue;
            }

            $attributes = [
                'current_price' => $price,
                'original_price' => $originalPrice,
                'discount_percent' => $discount ?? 0,
                'is_real_price' => true,
                'url' => $url,
                'affiliate_url' => $url,
                'in_stock' => $inStock,
                'currency' => 'EUR',
                'platform' => 'PC',
                'region' => $region,
                'type' => 'key',
            ];

            $product = Product::where('game_id', $game->id)
                ->where('store_id', $store->id)
                ->first();

            if ($product) {
                $product->fill($attributes)->save();
                $updated++;
            } else {
                $product = Product::create(array_merge($attributes, [
                    'game_id' => $game->id,
                    'store_id' => $store->id,
                ]));
                $created++;
            }

            $latestHistory = PriceHistory::where('product_id', $product->id)
                ->orderByDesc('recorded_at')
                ->first();

            if (!$latestHistory || $latestHistory->price != $product->current_price) {
                PriceHistory::create([
                    'product_id' => $product->id,
                    'price' => $product->current_price,
                    'currency' => $product->currency,
                    'recorded_at' => now(),
                ]);
            }
        }

        $this->info("Gamivo import: created {$created}, updated {$updated} products.");

        Cache::flush();

        return self::SUCCESS;
    }

    private function extractGameTitle(string $gamivoName): string
    {
        $title = preg_replace('/\s*\([^)]*\)\s*(Steam Key|Key)\s*(GLOBAL|EUROPE|US|ASIA).*/i', '', $gamivoName);
        $title = preg_replace('/\s*(Steam Key|GOG Key|Xbox Live Key|PSN Key)\s*(GLOBAL|EUROPE|US|ASIA).*/i', '', $title);
        $title = trim($title);
        return $title;
    }

    private function extractRegion(string $name): string
    {
        $upper = strtoupper($name);
        $map = [
            'GLOBAL' => 'global',
            'EUROPE' => 'EU',
            '(EU)' => 'EU',
            'NORTH AMERICA' => 'US',
            '(US)' => 'US',
            '(USA)' => 'US',
            '(NA)' => 'US',
            'LATAM' => 'LATAM',
            'LATIN AMERICA' => 'LATAM',
            'RUSSIA' => 'RU',
            '(RU)' => 'RU',
            'CIS' => 'CIS',
            'ASIA' => 'ASIA',
            'APAC' => 'ASIA',
        ];
        foreach ($map as $needle => $region) {
            if (str_contains($upper, $needle)) {
                return $region;
            }
        }
        return 'global';
    }

    private function findGame(string $title): ?Game
    {
        $game = Game::whereRaw('LOWER(title) = LOWER(?)', [$title])->first();
        if ($game) {
            return $game;
        }

        $game = Game::whereRaw('LOWER(title) LIKE LOWER(?)', ['%' . $title . '%'])
            ->orderByRaw('LENGTH(title)')
            ->first();
        if ($game) {
            return $game;
        }

        $words = array_filter(explode(' ', strtolower($title)));
        if (count($words) >= 3) {
            $games = Game::whereRaw('LOWER(title) LIKE LOWER(?)', ['%' . implode('%', array_slice($words, 0, 3)) . '%'])
                ->get();
            if ($games->count() === 1) {
                return $games->first();
            }
        }

        return null;
    }
}
