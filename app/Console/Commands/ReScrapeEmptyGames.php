<?php

namespace App\Console\Commands;

use App\Jobs\FetchPricesForGame;
use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReScrapeEmptyGames extends Command
{
    protected $signature = 'games:rescrape-empty {--limit=50}';

    protected $description = 'Re-scrape prices for games that have no products';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $games = Game::doesntHave('products')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($games->isEmpty()) {
            $this->info('No games without prices found.');
            return self::SUCCESS;
        }

        $this->info("Found {$games->count()} games without prices. Processing...");

        $withPrices = 0;
        $bar = $this->output->createProgressBar($games->count());
        $bar->start();

        foreach ($games as $game) {
            FetchPricesForGame::dispatchSync($game);

            $game->refresh();
            if ($game->products()->exists()) {
                $withPrices++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Processed: {$games->count()}, Got prices: {$withPrices}");
        Log::info("Re-scrape empty games completed", [
            'processed' => $games->count(),
            'with_prices' => $withPrices,
        ]);

        return self::SUCCESS;
    }
}
