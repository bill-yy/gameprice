# Task 19: Re-scrape games with no prices

## Context
GamePrice Laravel 12. Some games were imported before the synchronous scraping fix and have no prices.

## What exists
- `app/Jobs/FetchPricesForGame.php` — job that scrapes prices for a game
- `app/Console/Commands/UpdateAllPrices.php` — currently empty stub
- `app/Models/Game.php` — has `products()` relationship
- `app/Models/Product.php` — store, price, url, game_id

## What to build
1. Create an Artisan Command `app/Console/Commands/ReScrapeEmptyGames.php` that:
   - Finds all games with 0 products
   - Loops through them and dispatches `FetchPricesForGame` job for each
   - Accepts optional `--limit=N` flag (default 50)
   - Shows progress in console
2. Register the command in `routes/console.php` with a schedule (daily at 3 AM)

## Important constraints
- Use existing `FetchPricesForGame` job — do NOT modify it
- Do NOT modify existing working code
- Use `dispatchSync` for immediate execution within the command loop
- Log how many games were processed and how many got prices
