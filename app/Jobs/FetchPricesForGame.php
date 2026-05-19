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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchPricesForGame implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Game $game) {}

    private const MAX_DURATION_SECONDS = 25;
    private const SCRAPER_TIMEOUT_SECONDS = 15;
    private const RETRY_DELAY_MICROSECONDS = 2_000_000;

    private const SCRAPERS = [
        'cheapshark' => CheapSharkScraper::class,
        'eneba' => EnebaScraper::class,
        'instant-gaming' => InstantGamingScraper::class,
        'g2a' => G2AScraper::class,
        'kinguin' => KinguinScraper::class,
        'cdkeys' => CDKeysScraper::class,
        'psn-store' => PSNStoreScraper::class,
        'xbox-store' => XboxStoreScraper::class,
    ];

    private const STORE_NAMES = [
        'cheapshark' => 'CheapShark',
        'eneba' => 'Eneba',
        'instant-gaming' => 'Instant Gaming',
        'g2a' => 'G2A',
        'kinguin' => 'Kinguin',
        'cdkeys' => 'CDKeys',
        'psn-store' => 'PlayStation Store',
        'xbox-store' => 'Xbox Store',
    ];

    public function handle(): void
    {
        Log::info("FetchPricesForGame: START for game", [
            'game_id' => $this->game->id,
            'game_title' => $this->game->title,
        ]);

        $this->cacheCurrentPrices();

        $startTime = microtime(true);
        $metrics = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'retried' => 0, 'retry_success' => 0];
        $foundAnyPrice = false;

        try {
            foreach (self::SCRAPERS as $slug => $scraperClass) {
                $elapsed = microtime(true) - $startTime;
                if ($elapsed >= self::MAX_DURATION_SECONDS) {
                    Log::warning("FetchPricesForGame: time limit reached, skipping remaining scrapers", [
                        'game_id' => $this->game->id,
                        'elapsed' => round($elapsed, 1),
                        'last_scraper' => $slug,
                    ]);
                    $metrics['skipped']++;
                    break;
                }

                $result = $this->runScraperWithRetry($slug, $scraperClass, $startTime);

                if ($result === 'retried_ok') {
                    $metrics['retry_success']++;
                }

                if ($result === null || $result === 'timeout' || $result === 'retried_ok') {
                    if ($result === null) {
                        $metrics['failed']++;
                    } elseif ($result === 'timeout') {
                        $metrics['failed']++;
                    }
                    continue;
                }

                if (is_array($result)) {
                    $this->saveProduct($slug, $result);
                    $metrics['success']++;
                    $foundAnyPrice = true;
                } else {
                    $metrics['failed']++;
                }

                usleep(300_000);
            }
        } catch (Throwable $e) {
            Log::critical("FetchPricesForGame: FATAL ERROR in main loop for game {$this->game->id}", [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        if (!$foundAnyPrice) {
            $this->applyFallbackPrices();
        }

        $totalElapsed = microtime(true) - $startTime;

        Log::info("FetchPricesForGame: COMPLETED for game {$this->game->id}", [
            'elapsed' => round($totalElapsed, 2) . 's',
            'metrics' => $metrics,
            'fallback_used' => !$foundAnyPrice,
        ]);
    }

    private function runScraperWithRetry(string $slug, string $scraperClass, float $startTime): mixed
    {
        $scraperStart = microtime(true);

        Log::info("FetchPricesForGame: BEFORE {$slug}", [
            'game_id' => $this->game->id,
            'game_title' => $this->game->title,
            'scraper_class' => $scraperClass,
        ]);

        $result = $this->executeScraper($slug, $scraperClass, $scraperStart, $startTime);

        if ($result === 'timeout') {
            return 'timeout';
        }

        if ($result !== null) {
            return $result;
        }

        $scraperElapsed = microtime(true) - $scraperStart;
        $globalElapsed = microtime(true) - $startTime;

        if ($globalElapsed + 2 >= self::MAX_DURATION_SECONDS) {
            Log::info("FetchPricesForGame: no time for retry of {$slug}", [
                'game_id' => $this->game->id,
                'elapsed' => round($globalElapsed, 1),
            ]);
            return null;
        }

        Log::info("FetchPricesForGame: RETRYING {$slug} after failure", [
            'game_id' => $this->game->id,
            'first_attempt_ms' => round($scraperElapsed * 1000),
        ]);

        usleep(self::RETRY_DELAY_MICROSECONDS);

        $retryStart = microtime(true);
        $retryResult = $this->executeScraper($slug, $scraperClass, $retryStart, $startTime, true);

        if ($retryResult !== null && $retryResult !== 'timeout') {
            return 'retried_ok';
        }

        return null;
    }

    private function executeScraper(string $slug, string $scraperClass, float $scraperStart, float $globalStart, bool $isRetry = false): mixed
    {
        try {
            $scraper = new $scraperClass;
            $result = $scraper->search($this->game->title);

            $elapsed = round((microtime(true) - $scraperStart) * 1000);

            Log::info("FetchPricesForGame: AFTER {$slug}" . ($isRetry ? ' (retry)' : ''), [
                'game_id' => $this->game->id,
                'result' => $result === null ? 'NULL' : 'FOUND',
                'price_eur' => $result['price_eur'] ?? null,
                'elapsed_ms' => $elapsed,
            ]);

            return $result;
        } catch (Throwable $e) {
            $elapsed = round((microtime(true) - $scraperStart) * 1000);

            Log::error("FetchPricesForGame: SCRAPER {$slug} FAILED" . ($isRetry ? ' (retry)' : '') . " for game {$this->game->id}", [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'elapsed_ms' => $elapsed,
            ]);

            return null;
        }
    }

    private function saveProduct(string $slug, array $result): void
    {
        $store = Store::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => self::STORE_NAMES[$slug] ?? ucfirst(str_replace('-', ' ', $slug)),
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
                'price_fetched_at' => now(),
            ],
        );

        Log::info("FetchPricesForGame: {$slug} saved price for game {$this->game->id}", [
            'price' => $result['price_eur'],
            'platform' => $platform,
        ]);
    }

    private function cacheCurrentPrices(): void
    {
        $currentRealProducts = Product::where('game_id', $this->game->id)
            ->where('is_real_price', true)
            ->with('store')
            ->get();

        if ($currentRealProducts->isEmpty()) {
            return;
        }

        $cacheKey = "game_prices_fallback:{$this->game->id}";
        $priceData = $currentRealProducts->map(fn ($p) => [
            'store_slug' => $p->store?->slug,
            'current_price' => $p->current_price,
            'original_price' => $p->original_price,
            'discount_percent' => $p->discount_percent,
            'url' => $p->url,
            'platform' => $p->platform,
            'region' => $p->region,
            'type' => $p->type,
            'in_stock' => $p->in_stock,
        ])->toArray();

        Cache::put($cacheKey, $priceData, now()->addDays(7));
    }

    private function applyFallbackPrices(): void
    {
        $cacheKey = "game_prices_fallback:{$this->game->id}";
        $lastPrices = Cache::get($cacheKey);

        if ($lastPrices && is_array($lastPrices) && !empty($lastPrices)) {
            Log::info("FetchPricesForGame: using cached fallback prices for game {$this->game->id}", [
                'cached_count' => count($lastPrices),
            ]);

            foreach ($lastPrices as $priceData) {
                $store = Store::where('slug', $priceData['store_slug'])->first();
                if (!$store) {
                    continue;
                }

                Product::updateOrCreate(
                    ['game_id' => $this->game->id, 'store_id' => $store->id, 'platform' => $priceData['platform'] ?? 'PC'],
                    [
                        'current_price' => $priceData['current_price'],
                        'original_price' => $priceData['original_price'],
                        'discount_percent' => $priceData['discount_percent'] ?? 0,
                        'url' => $priceData['url'],
                        'affiliate_url' => $priceData['url'],
                        'is_real_price' => false,
                        'currency' => 'EUR',
                        'platform' => $priceData['platform'] ?? 'PC',
                        'region' => $priceData['region'] ?? 'global',
                        'type' => $priceData['type'] ?? 'key',
                        'in_stock' => $priceData['in_stock'] ?? true,
                        'price_fetched_at' => now(),
                    ],
                );
            }
            return;
        }

        Log::warning("FetchPricesForGame: no real or fallback prices for game {$this->game->id}", [
            'game_title' => $this->game->title,
        ]);
    }
}
