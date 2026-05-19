# Task 30G: Create scraper diagnostics endpoint

## Context
GamePrice production shows only 1 store offer per game (Eneba). The FetchPricesForGame job should run multiple scrapers but apparently only one works. Need a diagnostics endpoint to see which scrapers succeed/fail in production.

## What exists
- `app/Jobs/FetchPricesForGame.php` — runs multiple scrapers, catches exceptions
- `app/Services/Scrapers/*.php` — scraper services (CheapShark, Eneba, InstantGaming, G2A, Kinguin, etc.)
- `routes/web.php` — has debug routes
- The queue worker runs in Docker via supervisord

## What to build
Create a new GET route `/debug/scrapers/{game}` that:
1. Finds the game by slug
2. Iterates through ALL scrapers (same list as FetchPricesForGame)
3. For each scraper, calls `->search($game->title)` with a 15s timeout
4. Records: success/failure, result data (if any), error message (if failed), elapsed time
5. Returns JSON with full diagnostic info
6. Also returns count of existing products for this game in the database

Create `app/Http/Controllers/DebugScraperController.php` with a `diagnose(Game $game)` method.

In `routes/web.php`, add:
```php
Route::get('/debug/scrapers/{game:slug}', [DebugScraperController::class, 'diagnose']);
```

The endpoint should NOT be cached and should run synchronously (not via queue).

## Important constraints
- Do NOT modify existing scrapers or the FetchPricesForGame job
- Keep the endpoint lightweight — just diagnostic info
- Commit: "Task 30G: Add scraper diagnostics endpoint"
