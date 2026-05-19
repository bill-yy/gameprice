<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\IsThereAnyDealScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScrapeRealPrices extends Command
{
    protected $signature = 'prices:scrape-real
                            {--limit=50 : Max number of games to process}
                            {--source=all : Comma-separated sources: eneba,cheapshark,itad,all}';

    protected $description = 'Scrape REAL prices from Eneba, CheapShark, and IsThereAnyDeal';

    public function handle(): int
    {
        $sources = array_map('trim', explode(',', $this->option('source')));
        $allSources = in_array('all', $sources);

        $useEneba = $allSources || in_array('eneba', $sources);
        $useCheapShark = $allSources || in_array('cheapshark', $sources);
        $useItad = $allSources || in_array('itad', $sources);

        $games = Game::where('is_active', true)
            ->limit((int) $this->option('limit'))
            ->get();

        if ($games->isEmpty()) {
            $this->warn('No active games found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$games->count()} games...");
        $bar = $this->output->createProgressBar($games->count());
        $bar->start();

        $stats = [
            'eneba' => ['found' => 0, 'updated' => 0],
            'cheapshark' => ['found' => 0, 'updated' => 0],
            'itad' => ['found' => 0, 'updated' => 0],
        ];

        foreach ($games as $game) {
            if ($useEneba) {
                $this->scrapeEneba($game, $stats['eneba']);
            }

            if ($useCheapShark) {
                $this->scrapeCheapShark($game, $stats['cheapshark']);
            }

            if ($useItad) {
                $this->scrapeItad($game, $stats['itad']);
            }

            // Rate limiting: sleep 500ms between games
            usleep(500000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Source', 'Prices Found', 'Products Updated'],
            [
                ['Eneba', $stats['eneba']['found'], $stats['eneba']['updated']],
                ['CheapShark', $stats['cheapshark']['found'], $stats['cheapshark']['updated']],
                ['IsThereAnyDeal', $stats['itad']['found'], $stats['itad']['updated']],
            ]
        );

        Cache::flush();
        $this->info('Cache flushed.');

        return self::SUCCESS;
    }

    private function scrapeEneba(Game $game, array &$stats): void
    {
        try {
            $scraper = new EnebaScraper();
            $result = $scraper->search($game->title);

            if (! $result) {
                return;
            }

            $stats['found']++;

            $store = Store::firstOrCreate(
                ['slug' => 'eneba'],
                [
                    'name' => 'Eneba',
                    'website' => 'https://www.eneba.com',
                    'is_official' => false,
                    'is_active' => true,
                ]
            );

            $this->saveProduct($game, $store, $result);
            $stats['updated']++;
        } catch (\Throwable $e) {
            Log::error('Eneba scrape failed', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function scrapeCheapShark(Game $game, array &$stats): void
    {
        try {
            $scraper = new CheapSharkScraper();
            $result = $scraper->search($game->title);

            if (! $result) {
                return;
            }

            $stats['found']++;

            // Map CheapShark store ID to our store
            $storeSlug = $scraper->mapStoreId($result['store_id'] ?? null);

            if (! $storeSlug) {
                // Use a generic cheapshark store if unknown
                $storeSlug = 'cheapshark';
            }

            $store = Store::firstOrCreate(
                ['slug' => $storeSlug],
                [
                    'name' => ucwords(str_replace('-', ' ', $storeSlug)),
                    'website' => 'https://www.cheapshark.com',
                    'is_official' => true,
                    'is_active' => true,
                ]
            );

            $this->saveProduct($game, $store, $result);
            $stats['updated']++;
        } catch (\Throwable $e) {
            Log::error('CheapShark scrape failed', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function scrapeItad(Game $game, array &$stats): void
    {
        try {
            $scraper = new IsThereAnyDealScraper();

            if (! $scraper->isConfigured()) {
                return;
            }

            $results = $scraper->search($game->title);

            if (empty($results)) {
                return;
            }

            foreach ($results as $result) {
                $stats['found']++;

                $store = Store::firstOrCreate(
                    ['slug' => $result['store_slug']],
                    [
                        'name' => $result['store'],
                        'website' => 'https://' . $result['store_slug'] . '.com',
                        'is_official' => false,
                        'is_active' => true,
                    ]
                );

                $this->saveProduct($game, $store, [
                    'name' => $game->title,
                    'price_eur' => $result['price_eur'],
                    'original_price_eur' => $result['original_price_eur'] ?? $result['price_eur'],
                    'discount_percent' => $result['discount_percent'] ?? 0,
                    'url' => $result['url'],
                ]);

                $stats['updated']++;
            }
        } catch (\Throwable $e) {
            Log::error('ITAD scrape failed', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveProduct(Game $game, Store $store, array $data): void
    {
        Product::updateOrCreate(
            [
                'game_id' => $game->id,
                'store_id' => $store->id,
            ],
            [
                'current_price' => $data['price_eur'],
                'original_price' => $data['original_price_eur'] ?? $data['price_eur'],
                'discount_percent' => $data['discount_percent'] ?? 0,
                'url' => $data['url'],
                'affiliate_url' => $data['url'],
                'is_real_price' => true,
                'currency' => 'EUR',
                'platform' => 'PC',
                'region' => 'global',
                'type' => 'key',
                'in_stock' => true,
            ]
        );
    }
}
