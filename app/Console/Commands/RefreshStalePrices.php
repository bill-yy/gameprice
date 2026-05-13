<?php

namespace App\Console\Commands;

use App\Jobs\FetchPricesForGame;
use App\Models\Game;
use Illuminate\Console\Command;

class RefreshStalePrices extends Command
{
    protected $signature = 'prices:refresh-stale';

    protected $description = 'Refresh prices for games with stale or missing real prices';

    public function handle(): int
    {
        $games = Game::query()
            ->where(function ($q) {
                $q->whereDoesntHave('products', fn ($q) => $q->where('is_real_price', true))
                  ->orWhereHas('products', function ($q) {
                      $q->where('is_real_price', true)
                        ->where(function ($sq) {
                            $sq->whereNull('price_fetched_at')
                               ->orWhere('price_fetched_at', '<', now()->subHours(24));
                        });
                  });
            })
            ->withCount(['products as real_prices_count' => fn ($q) => $q->where('is_real_price', true)])
            ->orderByRaw('CASE WHEN real_prices_count = 0 THEN 1 ELSE 0 END')
            ->orderByRaw('(SELECT MAX(price_fetched_at) FROM products WHERE products.game_id = games.id AND products.is_real_price = true) ASC NULLS LAST')
            ->limit(50)
            ->get();

        foreach ($games as $game) {
            FetchPricesForGame::dispatch($game);
        }

        $this->info("Dispatched price refresh for {$games->count()} games.");

        return self::SUCCESS;
    }
}
