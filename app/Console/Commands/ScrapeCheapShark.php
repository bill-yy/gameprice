<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScrapeCheapShark extends Command
{
    protected $signature = 'prices:scrape-cheapshark {--pages=50 : Number of pages to fetch}';

    protected $description = 'Scrape real prices from CheapShark API';

    private const string BASE_URL = 'https://www.cheapshark.com/api/1.0';

    private array $storeMap = [
        '3'  => 'green-man-gaming',
        '11' => 'humble-bundle',
        '15' => 'fanatical',
    ];

    public function handle(): int
    {
        $maxPages = (int) $this->option('pages');
        $this->info("Fetching up to {$maxPages} pages from CheapShark...");

        $createdGames = 0;
        $updatedGames = 0;
        $createdProducts = 0;
        $updatedProducts = 0;

        // Preload stores
        $stores = Store::whereIn('slug', array_values($this->storeMap))
            ->get()
            ->keyBy('slug');

        for ($page = 0; $page < $maxPages; $page++) {
            $deals = $this->fetchDeals($page);

            if (empty($deals)) {
                $this->info("No more deals at page {$page}. Stopping.");
                break;
            }

            $this->info("Page {$page}: " . count($deals) . ' deals');

            foreach ($deals as $deal) {
                $result = $this->processDeal($deal, $stores);
                if ($result['game_created']) $createdGames++;
                if ($result['game_updated']) $updatedGames++;
                if ($result['product_created']) $createdProducts++;
                if ($result['product_updated']) $updatedProducts++;
            }
        }

        $this->newLine();
        $this->info("Done! Games: +{$createdGames} created, {$updatedGames} updated. Products: +{$createdProducts} created, {$updatedProducts} updated.");

        Cache::flush();
        $this->info('Cache flushed.');

        return self::SUCCESS;
    }

    private function fetchDeals(int $page): array
    {
        $url = self::BASE_URL . '/deals?pageSize=60&pageNumber=' . $page;
        $this->info("Fetching: {$url}");

        try {
            $response = Http::timeout(30)->get($url);

            $this->info("Response status: " . $response->status());
            $this->info("Response body length: " . strlen($response->body()));

            if ($response->failed()) {
                $this->warn("Request failed: " . $response->status());
                Log::warning('CheapShark deals request failed', ['page' => $page, 'status' => $response->status()]);
                return [];
            }

            $json = $response->json();
            $this->info("Decoded items: " . (is_array($json) ? count($json) : 'not array'));

            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            $this->warn("Exception: " . $e->getMessage());
            Log::warning('CheapShark deals connection error', ['page' => $page, 'error' => $e->getMessage()]);
            return [];
        }
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
        $savings = isset($deal['savings']) ? (float) $deal['savings'] : 0;

        if (! $steamAppId || ! isset($this->storeMap[$storeId]) || ! $title || ! $salePrice) {
            return $result;
        }

        $storeSlug = $this->storeMap[$storeId];
        $store = $stores->get($storeSlug);

        if (! $store) {
            return $result;
        }

        // Find or create game
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

            $slug = \Illuminate\Support\Str::slug($title);
            $existingSlug = Game::where('slug', $slug)->first();
            if ($existingSlug) {
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
            // Update cover image if missing
            if (! $game->cover_image) {
                $game->cover_image = "https://cdn.cloudflare.steamstatic.com/steam/apps/{$steamAppId}/header.jpg";
                $game->save();
                $result['game_updated'] = true;
            }
        }

        // Create or update product
        $discountPercent = $normalPrice > 0
            ? (int) round((1 - ($salePrice / $normalPrice)) * 100)
            : (int) round($savings);

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
