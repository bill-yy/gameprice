<?php

namespace App\Jobs;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use App\Services\Scrapers\CDKeysScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use App\Services\Scrapers\PSNStoreScraper;
use App\Services\Scrapers\XboxStoreScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchPricesForGame implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Game $game) {}

    private const MAX_DURATION_SECONDS = 25;

    public function handle(): void
    {
        $scrapers = [
            'cheapshark' => CheapSharkScraper::class,
            'eneba' => EnebaScraper::class,
            'instant-gaming' => InstantGamingScraper::class,
            'g2a' => G2AScraper::class,
            'kinguin' => KinguinScraper::class,
            'cdkeys' => CDKeysScraper::class,
            'psn-store' => PSNStoreScraper::class,
            'xbox-store' => XboxStoreScraper::class,
        ];

        $storeNames = [
            'cheapshark' => 'CheapShark',
            'eneba' => 'Eneba',
            'instant-gaming' => 'Instant Gaming',
            'g2a' => 'G2A',
            'kinguin' => 'Kinguin',
            'cdkeys' => 'CDKeys',
            'psn-store' => 'PlayStation Store',
            'xbox-store' => 'Xbox Store',
        ];

        $startTime = microtime(true);

        foreach ($scrapers as $slug => $scraperClass) {
            $elapsed = microtime(true) - $startTime;
            if ($elapsed >= self::MAX_DURATION_SECONDS) {
                Log::warning("FetchPricesForGame: time limit reached, skipping remaining scrapers", [
                    'game_id' => $this->game->id,
                    'elapsed' => round($elapsed, 1),
                    'last_scraper' => $slug,
                ]);
                break;
            }

            try {
                $scraper = new $scraperClass;
                $result = $scraper->search($this->game->title);

                if ($result !== null) {
                    $store = Store::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => $storeNames[$slug] ?? ucfirst(str_replace('-', ' ', $slug)),
                            'is_active' => true,
                        ],
                    );

                    $platform = $result['platform'] ?? 'PC';
                    $type = in_array($platform, ['PS5', 'PS4', 'Xbox Series X|S', 'Xbox One', 'Nintendo Switch']) ? 'digital' : 'key';

                    Product::updateOrCreate(
                        ['game_id' => $this->game->id, 'store_id' => $store->id, 'platform' => $platform],
                        [
                            'current_price' => $result['price_eur'],
                            'original_price' => $result['original_price_eur'],
                            'discount_percent' => $result['discount_percent'],
                            'url' => $result['url'],
                            'affiliate_url' => $result['url'],
                            'is_real_price' => true,
                            'currency' => 'EUR',
                            'platform' => $platform,
                            'region' => $result['region'] ?? 'global',
                            'type' => $type,
                            'in_stock' => $result['in_stock'] ?? true,
                        ],
                    );

                    Log::info("FetchPricesForGame: {$slug} found price for game {$this->game->id}", [
                        'price' => $result['price_eur'],
                        'platform' => $platform,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning("Scraper {$slug} failed for game {$this->game->id}", [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            usleep(100_000);
        }

        $totalElapsed = microtime(true) - $startTime;
        Log::info("FetchPricesForGame completed for game {$this->game->id}", [
            'elapsed' => round($totalElapsed, 2) . 's',
        ]);
    }
}
