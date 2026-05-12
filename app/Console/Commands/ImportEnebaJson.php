<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportEnebaJson extends Command
{
    protected $signature = 'prices:import-eneba-json';

    protected $description = 'Import Eneba prices from data/eneba_prices.json';

    public function handle(): int
    {
        $path = base_path('data/eneba_prices.json');

        if (! file_exists($path)) {
            $this->warn("Eneba JSON not found at {$path}");
            return self::SUCCESS;
        }

        $items = json_decode(file_get_contents($path), true);

        if (empty($items)) {
            $this->warn('No Eneba prices found in JSON.');
            return self::SUCCESS;
        }

        $store = Store::firstOrCreate(
            ['slug' => 'eneba'],
            [
                'name' => 'Eneba',
                'website' => 'https://www.eneba.com',
                'is_active' => true,
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
            $inStock = $item['in_stock'] ?? true;

            if (! $name || ! $price) {
                continue;
            }

            // Extract game title from Eneba product name (remove platform/region info)
            $cleanTitle = $this->extractGameTitle($name);

            // Find matching game
            $game = $this->findGame($cleanTitle);

            if (! $game) {
                Log::debug("Eneba import: no game match for '{$cleanTitle}' (from '{$name}')");
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

        $this->info("Eneba import: created {$created}, updated {$updated} products.");

        Cache::flush();

        return self::SUCCESS;
    }

    private function extractGameTitle(string $enebaName): string
    {
        // Remove common suffixes like "(PC) Steam Key GLOBAL", "Steam Key EUROPE", etc.
        $title = preg_replace('/\s*\([^)]*\)\s*(Steam Key|Key)\s*(GLOBAL|EUROPE|US|ASIA).*/i', '', $enebaName);
        $title = preg_replace('/\s*(Steam Key|GOG Key|Xbox Live Key|PSN Key)\s*(GLOBAL|EUROPE|US|ASIA).*/i', '', $title);
        $title = trim($title);
        return $title;
    }

    private function findGame(string $title): ?Game
    {
        // Exact match
        $game = Game::whereRaw('LOWER(title) = LOWER(?)', [$title])->first();
        if ($game) {
            return $game;
        }

        // Contains match
        $game = Game::whereRaw('LOWER(title) LIKE LOWER(?)', ['%' . $title . '%'])
            ->orderByRaw('LENGTH(title)')
            ->first();
        if ($game) {
            return $game;
        }

        // Word-by-word match (at least 3 words in common)
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
