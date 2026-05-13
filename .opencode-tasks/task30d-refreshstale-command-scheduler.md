# Task 30D: Create RefreshStalePrices command and configure scheduler

## Context
GamePrice Laravel 12. Need a scheduled command to periodically refresh stale prices across all games.

## What exists
- `app/Console/Commands/` directory with existing commands
- `app/Console/Kernel.php` (Laravel scheduler)
- `FetchPricesForGame` job exists
- `Product` model has `price_fetched_at` field
- Supervisor already runs queue worker in Docker

## What to build
1. Create command `app/Console/Commands/RefreshStalePrices.php`:
   - Signature: `prices:refresh-stale`
   - Description: 'Refresh prices for games with stale or missing real prices'
   - Find games where ANY product with `is_real_price=true` has `price_fetched_at` older than 24h OR has no real prices at all
   - Order by oldest `price_fetched_at` first (games without prices last)
   - Limit to 50 games per run
   - For each game, dispatch `FetchPricesForGame` job
   - Log how many games were queued
2. Register command in `app/Console/Kernel.php`:
   - `schedule->command('prices:refresh-stale')->everySixHours()`
3. Commit: "Task 30D: Add RefreshStalePrices command and scheduler"

## Exact command code pattern
```php
class RefreshStalePrices extends Command
{
    protected $signature = 'prices:refresh-stale';
    protected $description = 'Refresh prices for games with stale or missing real prices';

    public function handle()
    {
        $games = Game::query()
            ->where(function ($q) {
                $q->whereDoesntHave('products', fn($q) => $q->where('is_real_price', true))
                  ->orWhereHas('products', function ($q) {
                      $q->where('is_real_price', true)
                        ->where(function ($sq) {
                            $sq->whereNull('price_fetched_at')
                               ->orWhere('price_fetched_at', '<', now()->subHours(24));
                        });
                  });
            })
            ->withCount(['products as real_prices_count' => fn($q) => $q->where('is_real_price', true)])
            ->orderByRaw('CASE WHEN real_prices_count = 0 THEN 1 ELSE 0 END')
            ->orderByRaw('(SELECT MAX(price_fetched_at) FROM products WHERE products.game_id = games.id AND products.is_real_price = true) ASC NULLS LAST')
            ->limit(50)
            ->get();

        foreach ($games as $game) {
            FetchPricesForGame::dispatch($game);
        }

        $this->info("Dispatched price refresh for {$games->count()} games.");
    }
}
```

## Important constraints
- Use `dispatch()` not `dispatchSync()` (background processing)
- Limit 50 games per run to avoid overwhelming queue
- Prioritize games with oldest prices first
- Commit separately
