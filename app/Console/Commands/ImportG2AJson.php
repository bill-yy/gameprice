<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportG2AJson extends Command
{
    protected $signature = 'g2a:import-json';

    protected $description = 'Import G2A prices from data/g2a_prices.json';

    public function handle(): int
    {
        $path = base_path('data/g2a_prices.json');

        if (!file_exists($path)) {
            $this->warn("G2A JSON not found at {$path}");
            return self::SUCCESS;
        }

        $items = json_decode(file_get_contents($path), true);

        if (empty($items)) {
            $this->warn('No G2A prices found in JSON.');
            return self::SUCCESS;
        }

        $store = Store::firstOrCreate(
            ['slug' => 'g2a'],
            [
                'name' => 'G2A',
                'website' => 'https://www.g2a.com',
                'logo_url' => 'https://www.g2a.com/favicon.ico',
                'is_active' => true,
                'is_official' => false,
            ]
        );

        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $name = $item['name'] ?? null;
            $price = $item['price_eur'] ?? null;
            $originalPrice = $item['original_price_eur'] ?? $price;
            $discount = $item['discount_percent'] ?? null;
            $url = $item['url'] ?? null;
            $region = $item['region'] ?? 'global';
            $inStock = $item['in_stock'] ?? true;

            if (!$name || !$price) {
                continue;
            }

            $cleanTitle = $this->extractGameTitle($name);

            $game = $this->findGame($cleanTitle);

            if (!$game) {
                Log::debug("G2A import: no game match for '{$cleanTitle}' (from '{$name}')");
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

        $this->info("G2A import: created {$created}, updated {$updated} products.");

        Cache::flush();

        return self::SUCCESS;
    }

    private function extractGameTitle(string $g2aName): string
    {
        $title = preg_replace('/\s*\([^)]*\)\s*(Steam Key|Key)\s*(GLOBAL|EUROPE|US|ASIA).*/i', '', $g2aName);
        $title = preg_replace('/\s*(Steam Key|GOG Key|Xbox Live Key|PSN Key)\s*(GLOBAL|EUROPE|US|ASIA).*/i', '', $title);
        $title = preg_replace('/\s*-\s*(Steam|GOG|Epic Games)\s*(Key|Account|Gift).*/i', '', $title);
        return trim($title);
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
