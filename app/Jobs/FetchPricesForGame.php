<?php

namespace App\Jobs;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchPricesForGame implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Game $game) {}

    public function handle(): void
    {
        $scrapers = [
            'eneba' => EnebaScraper::class,
            'instant-gaming' => InstantGamingScraper::class,
            'cheapshark' => CheapSharkScraper::class,
            'g2a' => G2AScraper::class,
            'kinguin' => KinguinScraper::class,
        ];

        foreach ($scrapers as $slug => $scraperClass) {
            try {
                $scraper = new $scraperClass;
                $result = $scraper->search($this->game->title);

                if ($result !== null) {
                    $store = Store::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => ucfirst($slug), 'is_active' => true],
                    );

                    Product::updateOrCreate(
                        ['game_id' => $this->game->id, 'store_id' => $store->id],
                        [
                            'current_price' => $result['price_eur'],
                            'original_price' => $result['original_price_eur'],
                            'discount_percent' => $result['discount_percent'],
                            'url' => $result['url'],
                            'affiliate_url' => $result['url'],
                            'is_real_price' => true,
                            'currency' => 'EUR',
                            'platform' => 'PC',
                            'region' => $result['region'] ?? 'global',
                            'type' => 'key',
                            'in_stock' => $result['in_stock'] ?? true,
                        ],
                    );
                }
            } catch (Throwable $e) {
                Log::warning("Scraper {$slug} failed for game {$this->game->id}", [
                    'message' => $e->getMessage(),
                ]);
            }

            usleep(500_000);
        }

        Cache::flush();
    }
}
