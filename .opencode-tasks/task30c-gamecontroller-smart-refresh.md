# Task 30C: Smart price refresh in GameController::show()

## Context
GamePrice Laravel 12. `GameController::show()` at `app/Http/Controllers/GameController.php` loads game details from cache.

## What exists
- `GameController::show(Game $game)` at line 158
- Uses `Cache::remember($cacheKey, 1800, ...)` for 30 min cache
- Loads `products` relationship with store
- Does NOT check if prices are stale or missing
- `FetchPricesForGame` job exists and can be dispatched

## What to build
In `GameController::show()`:
1. After loading game data, check if the game has ANY products with `is_real_price = true`
2. If NO real prices exist, OR if the newest `price_fetched_at` is older than 24 hours:
   - Dispatch `FetchPricesForGame` job in background (do NOT wait for it)
   - Clear the cache key so next load shows fresh data
3. Add this check BEFORE the `return Inertia::render(...)` line
4. Only dispatch if we haven't already dispatched recently (avoid duplicate jobs)

## Exact code pattern
```php
public function show(Game $game)
{
    $cacheKey = "games.show.{$game->slug}";
    
    $data = Cache::remember($cacheKey, 1800, function () use ($game) {
        // existing cache logic...
    });
    
    // Smart refresh check
    $lastFetched = Product::where('game_id', $game->id)
        ->where('is_real_price', true)
        ->max('price_fetched_at');
    
    $needsRefresh = !$lastFetched || $lastFetched->diffInHours(now()) >= 24;
    
    if ($needsRefresh) {
        FetchPricesForGame::dispatch($game);
        Cache::forget($cacheKey);
    }
    
    return Inertia::render('GameShow', [...]);
}
```

## Important constraints
- Must dispatch in background (use `dispatch()`, NOT `dispatchSync()`)
- Only check products with `is_real_price = true`
- Use `max('price_fetched_at')` to get the most recent fetch
- If no prices at all, also dispatch
- Keep all existing logic intact
- Commit: "Task 30C: Auto-dispatch price refresh for stale games"
