<?php

namespace App\Console\Commands;

use App\Services\Scrapers\AllKeyShopScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\GamesplanetScraper;
use App\Services\Scrapers\InstantGamingScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScrapePrices extends Command
{
    protected $signature = 'gameprice:scrape 
                            {--stores=all : Comma-separated list of stores or "all"}
                            {--query= : Optional query to search for specific games}';

    protected $description = 'Scrape game prices from all configured stores';

    private array $scrapers = [
        'cheapshark' => CheapSharkScraper::class,
        'eneba' => EnebaScraper::class,
        'instant-gaming' => InstantGamingScraper::class,
        'gamesplanet' => GamesplanetScraper::class,
        'allkeyshop' => AllKeyShopScraper::class,
    ];

    public function handle(): int
    {
        $storesInput = $this->option('stores');
        $query = $this->option('query');

        if ($storesInput === 'all') {
            $storesToRun = array_keys($this->scrapers);
        } else {
            $storesToRun = array_map('trim', explode(',', $storesInput));
        }

        $this->info('Starting price scraping...');
        $this->info('Stores: ' . implode(', ', $storesToRun));
        
        if ($query) {
            $this->info("Query: {$query}");
        }

        $totalResults = 0;
        $successStores = 0;
        $failedStores = 0;

        foreach ($storesToRun as $storeKey) {
            if (!isset($this->scrapers[$storeKey])) {
                $this->warn("Unknown store: {$storeKey}");
                continue;
            }

            $scraperClass = $this->scrapers[$storeKey];
            $storeName = $scraperClass::getStoreName();

            $this->info("\nScraping {$storeName}...");

            try {
                $scraper = new $scraperClass();
                
                if ($query) {
                    $results = $scraper->searchAll($query);
                } else {
                    // Default search for popular games to keep cache warm
                    $defaultQueries = ['the witcher 3', 'cyberpunk 2077', 'elden ring', 'hogwarts legacy'];
                    $results = [];
                    foreach ($defaultQueries as $q) {
                        $qResults = $scraper->searchAll($q);
                        $results = array_merge($results, $qResults);
                    }
                }

                $count = count($results);
                $totalResults += $count;
                $successStores++;

                $this->info("  ✅ {$storeName}: {$count} results");

                // Cache results for 1 hour
                if ($query) {
                    $cacheKey = "prices:{$storeKey}:" . md5($query);
                    Cache::put($cacheKey, $results, now()->addHour());
                }

                Log::info('Scraper completed', [
                    'store' => $storeKey,
                    'results' => $count,
                    'query' => $query,
                ]);
            } catch (\Throwable $e) {
                $failedStores++;
                $this->error("  ❌ {$storeName}: {$e->getMessage()}");
                Log::error('Scraper failed', [
                    'store' => $storeKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\n" . str_repeat('=', 50));
        $this->info("Scraping complete!");
        $this->info("Successful: {$successStores} | Failed: {$failedStores}");
        $this->info("Total results: {$totalResults}");

        return self::SUCCESS;
    }
}
