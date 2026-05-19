<?php

namespace App\Services;

use App\Services\Scrapers\CDKeysScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use App\Services\Scrapers\PSNStoreScraper;
use App\Services\Scrapers\XboxStoreScraper;
use Illuminate\Support\Facades\Log;
use Throwable;

class DebugScraperService
{
    public const SCRAPERS = [
        'cheapshark' => CheapSharkScraper::class,
        'eneba' => EnebaScraper::class,
        'instant-gaming' => InstantGamingScraper::class,
        'g2a' => G2AScraper::class,
        'kinguin' => KinguinScraper::class,
        'cdkeys' => CDKeysScraper::class,
        'psn-store' => PSNStoreScraper::class,
        'xbox-store' => XboxStoreScraper::class,
    ];

    private const TIMEOUT_PER_SCRAPER = 15;

    public function diagnose(string $gameTitle): array
    {
        $results = [];
        $totalStart = microtime(true);

        foreach (self::SCRAPERS as $slug => $scraperClass) {
            $results[$slug] = $this->runSingleScraper($slug, $scraperClass, $gameTitle);
        }

        $totalElapsed = round((microtime(true) - $totalStart) * 1000);

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        Log::info('DebugScraperService: diagnosis complete', [
            'game' => $gameTitle,
            'total_ms' => $totalElapsed,
            'successes' => $successCount,
            'failures' => $failCount,
        ]);

        return [
            'game_title' => $gameTitle,
            'total_elapsed_ms' => $totalElapsed,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failCount,
            ],
            'scrapers' => $results,
        ];
    }

    private function runSingleScraper(string $slug, string $scraperClass, string $gameTitle): array
    {
        $start = microtime(true);
        $timeout = self::TIMEOUT_PER_SCRAPER;

        $remaining = $timeout - (microtime(true) - $start);
        if ($remaining <= 0) {
            return [
                'success' => false,
                'price' => null,
                'error' => 'Skipped: global timeout reached',
                'elapsed_ms' => 0,
                'scraper_class' => $scraperClass,
            ];
        }

        try {
            set_error_handler(function ($severity, $message) {
                throw new \RuntimeException($message);
            });

            $scraper = new $scraperClass;
            $result = $scraper->search($gameTitle);

            restore_error_handler();

            $elapsed = round((microtime(true) - $start) * 1000);

            if ($result !== null) {
                Log::info("DebugScraperService: {$slug} succeeded", [
                    'game' => $gameTitle,
                    'price' => $result['price_eur'] ?? null,
                    'elapsed_ms' => $elapsed,
                ]);

                return [
                    'success' => true,
                    'price' => $result['price_eur'] ?? null,
                    'original_price' => $result['original_price_eur'] ?? null,
                    'discount_percent' => $result['discount_percent'] ?? null,
                    'url' => $result['url'] ?? null,
                    'platform' => $result['platform'] ?? null,
                    'region' => $result['region'] ?? 'global',
                    'in_stock' => $result['in_stock'] ?? true,
                    'error' => null,
                    'elapsed_ms' => $elapsed,
                    'scraper_class' => $scraperClass,
                ];
            }

            return [
                'success' => false,
                'price' => null,
                'error' => 'No results found',
                'elapsed_ms' => $elapsed,
                'scraper_class' => $scraperClass,
            ];
        } catch (Throwable $e) {
            restore_error_handler();

            $elapsed = round((microtime(true) - $start) * 1000);

            Log::warning("DebugScraperService: {$slug} threw exception", [
                'game' => $gameTitle,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'elapsed_ms' => $elapsed,
            ]);

            return [
                'success' => false,
                'price' => null,
                'error' => get_class($e) . ': ' . $e->getMessage(),
                'elapsed_ms' => $elapsed,
                'scraper_class' => $scraperClass,
            ];
        }
    }
}
