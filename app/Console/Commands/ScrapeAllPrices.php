<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use App\Services\Affiliates\EnebaService;
use App\Services\Affiliates\FanaticalService;
use App\Services\Affiliates\GreenManGamingService;
use App\Services\Affiliates\HumbleBundleService;
use App\Services\Affiliates\InstantGamingService;
use App\Services\Affiliates\KinguinService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScrapeAllPrices extends Command
{
    protected $signature = 'prices:scrape-all';

    protected $description = 'Scrape prices from all affiliate stores for active games';

    public function handle(
        EnebaService $eneba,
        InstantGamingService $instantGaming,
        FanaticalService $fanatical,
        GreenManGamingService $greenManGaming,
        KinguinService $kinguin,
        HumbleBundleService $humbleBundle,
    ): int {
        $services = [
            'eneba' => $eneba,
            'instant-gaming' => $instantGaming,
            'fanatical' => $fanatical,
            'green-man-gaming' => $greenManGaming,
            'kinguin' => $kinguin,
            'humble-bundle' => $humbleBundle,
        ];

        $stores = Store::whereIn('slug', array_keys($services))
            ->get()
            ->keyBy('slug');

        $games = Game::where('is_active', true)->get();

        if ($games->isEmpty()) {
            $this->info('No active games found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($games->count() * count($services));
        $bar->start();

        $updated = 0;
        $created = 0;

        foreach ($games as $game) {
            foreach ($services as $slug => $service) {
                $store = $stores->get($slug);

                if (! $store) {
                    $bar->advance();
                    continue;
                }

                try {
                    $data = $service->getPriceForGame($game);

                    $product = Product::where('game_id', $game->id)
                        ->where('store_id', $store->id)
                        ->first();

                    $attributes = [
                        'current_price' => $data['current_price'],
                        'original_price' => $data['original_price'],
                        'discount_percent' => $data['discount_percentage'],
                        'url' => $data['url'],
                        'affiliate_url' => $data['url'],
                        'in_stock' => $data['is_available'],
                        'currency' => 'EUR',
                        'platform' => 'PC',
                        'region' => 'global',
                        'type' => 'key',
                    ];

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
                } catch (\Throwable $e) {
                    Log::error('Affiliate scrape failed', [
                        'game_id' => $game->id,
                        'store_slug' => $slug,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Created {$created} products, updated {$updated} products.");

        Cache::flush();
        $this->info('Cache flushed successfully.');

        return self::SUCCESS;
    }
}
