# Task 28: Fix HTTP 500 on refreshPrices endpoint

## Context
GamePrice Laravel 12. The endpoint POST /juego/{game}/refresh-prices returns HTTP 500 for ALL games (tested Elden Ring, Battlefield 1). The controller has try-catch but still returns 500 instead of redirect with error. This suggests the error happens before try-catch or in a way that bypasses it.

## Root cause hypothesis
1. `dispatchSync()` may be failing because of queue driver misconfiguration
2. `Product::updateOrCreate()` may be failing due to type mismatches
3. Some scraper class may have a fatal error not caught by Throwable

## What exists
- `app/Http/Controllers/GameController.php` — `refreshPrices()` with try-catch + `dispatchSync(FetchPricesForGame)`
- `app/Jobs/FetchPricesForGame.php` — executes 8 scrapers with individual try-catch
- `routes/web.php` — route for refresh-prices

## What to fix

1. **Simplify `refreshPrices()` in GameController.php**:
   - Instead of `dispatchSync()`, execute scrapers DIRECTLY in the controller
   - Create a new private method `fetchPricesDirectly(Game $game)` that contains the scraping logic
   - This eliminates the queue/job layer as a source of 500 errors
   - Keep the same try-catch structure but with direct execution

2. **Add an API route without CSRF** (`routes/api.php`):
   - `POST /api/games/{game}/refresh-prices` 
   - Same logic but without CSRF middleware (uses `api` middleware group)
   - Returns JSON response: `{success: true, products_count: N}` or `{success: false, error: "..."}`
   - This allows testing the scrapers independently of CSRF issues

3. **Add better error handling**:
   - Log each scraper's result (success/failure)
   - If a scraper fails, continue with the next one (don't stop)
   - Return meaningful error messages

4. **Verify the CheapShark scraper works**:
   - The `searchGames()` fallback should work for AAA titles
   - Make sure `Product::updateOrCreate()` gets valid data types

## Important constraints
- Do NOT break existing pages that already show prices
- The direct execution must have the same 25-second time guard
- Must work for games with titles containing special chars (™, ®, etc.)
