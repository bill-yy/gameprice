<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportCheapSharkJson extends Command
{
    protected $signature = 'prices:import-cheapshark-json';

    protected $description = 'Import CheapShark deals from storage/app/cheapshark_deals.json';

    private array $storeMap = [
        '3'  => 'green-man-gaming',
        '11' => 'humble-bundle',
        '15' => 'fanatical',
    ];

    public function handle(): int
    {
        $path = storage_path('app/cheapshark_deals.json');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $json = file_get_contents($path);
        $deals = json_decode($json, true);

        if (! is_array($deals)) {
            $this->error('Invalid JSON');

            return self::FAILURE;
        }

        $this->info('Importing ' . count($deals) . ' deals...');

        $stores = Store::whereIn('slug', array_values($this->storeMap))
            ->get()
            ->keyBy('slug');

        $createdGames = 0;
        $updatedGames = 0;
        $createdProducts = 0;
        $updatedProducts = 0;

        $bar = $this->output->createProgressBar(count($deals));
        $bar->start();

        foreach ($deals as $deal) {
            $result = $this->processDeal($deal, $stores);
            if ($result['game_created']) $createdGames++;
            if ($result['game_updated']) $updatedGames++;
            if ($result['product_created']) $createdProducts++;
            if ($result['product_updated']) $updatedProducts++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done! Games: +{$createdGames} created, {$updatedGames} updated. Products: +{$createdProducts} created, {$updatedProducts} updated.");

        return self::SUCCESS;
    }

    private function processDeal(array $deal, $stores): array
    {
        $result = [
            'game_created' => false,
            'game_updated' => false,
            'product_created' => false,
            'product_updated' => false,
        ];

        $steamAppId = $deal['steamAppID'] ?? null;
        $storeId = (string) ($deal['storeID'] ?? '');
        $title = $deal['title'] ?? null;
        $salePrice = isset($deal['salePrice']) ? (float) $deal['salePrice'] : null;
        $normalPrice = isset($deal['normalPrice']) ? (float) $deal['normalPrice'] : null;

        if (! $steamAppId || ! isset($this->storeMap[$storeId]) || ! $title || ! $salePrice) {
            return $result;
        }

        $storeSlug = $this->storeMap[$storeId];
        $store = $stores->get($storeSlug);

        if (! $store) {
            return $result;
        }

        $game = Game::where('steam_app_id', $steamAppId)->first();

        if (! $game) {
            $releaseDate = null;
            if (! empty($deal['releaseDate']) && is_numeric($deal['releaseDate'])) {
                try {
                    $releaseDate = Carbon::createFromTimestamp($deal['releaseDate'])->format('Y-m-d');
                } catch (\Throwable) {
                    // ignore
                }
            }

            $slug = Str::slug($title);
            if (Game::where('slug', $slug)->exists()) {
                $slug .= '-' . $steamAppId;
            }

            $game = Game::create([
                'slug' => $slug,
                'title' => $title,
                'steam_app_id' => $steamAppId,
                'description' => null,
                'release_date' => $releaseDate,
                'cover_image' => "https://cdn.cloudflare.steamstatic.com/steam/apps/{$steamAppId}/header.jpg",
                'platforms' => ['windows'],
                'genres' => [],
                'developer' => null,
                'publisher' => null,
                'metacritic_score' => isset($deal['metacriticScore']) && $deal['metacriticScore'] ? (int) $deal['metacriticScore'] : null,
                'is_active' => true,
            ]);
            $result['game_created'] = true;
        } else {
            if (! $game->cover_image) {
                $game->cover_image = "https://cdn.cloudflare.steamstatic.com/steam/apps/{$steamAppId}/header.jpg";
                $game->save();
                $result['game_updated'] = true;
            }
        }

        $discountPercent = $normalPrice > 0
            ? (int) round((1 - ($salePrice / $normalPrice)) * 100)
            : 0;

        $product = Product::where('game_id', $game->id)
            ->where('store_id', $store->id)
            ->first();

        $attributes = [
            'current_price' => $salePrice,
            'original_price' => $normalPrice,
            'discount_percent' => $discountPercent,
            'url' => "https://www.cheapshark.com/redirect?dealID={$deal['dealID']}",
            'affiliate_url' => "https://www.cheapshark.com/redirect?dealID={$deal['dealID']}",
            'in_stock' => true,
            'currency' => 'EUR',
            'platform' => 'PC',
            'region' => 'global',
            'type' => 'key',
        ];

        if ($product) {
            $product->fill($attributes)->save();
            $result['product_updated'] = true;
        } else {
            Product::create(array_merge($attributes, [
                'game_id' => $game->id,
                'store_id' => $store->id,
            ]));
            $result['product_created'] = true;
        }

        return $result;
    }
}
