<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Steam\SteamStoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UpdateSteamGames extends Command
{
    protected $signature = 'steam:update-games {--limit=100}';

    protected $description = 'Update game details from Steam Store API';

    public function handle(SteamStoreService $steamStoreService): int
    {
        $limit = (int) $this->option('limit');

        $games = Game::whereNull('description')
            ->whereNotNull('steam_app_id')
            ->limit($limit)
            ->get();

        if ($games->isEmpty()) {
            $this->info('No games to update.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($games->count());
        $bar->start();

        $updated = 0;

        foreach ($games as $game) {
            if ($steamStoreService->updateGameFromSteam($game)) {
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} of {$games->count()} games.");

        Cache::flush();
        $this->info('Cache flushed successfully.');

        return self::SUCCESS;
    }
}
