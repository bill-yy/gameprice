# Task 30B: Improve FetchPricesForGame with throttling and price_fetched_at

## Context
GamePrice Laravel 12. Job `FetchPricesForGame` at `app/Jobs/FetchPricesForGame.php` currently runs all scrapers but has issues:
- No `price_fetched_at` is set when saving products
- Throttling is only 100ms between scrapers (too fast)
- If one scraper fails, it logs but continues (good), but we need better fallback
- Needs to track when each price was fetched

## What exists
- `app/Jobs/FetchPricesForGame.php` with 8 scrapers (cheapshark, eneba, instant-gaming, g2a, kinguin, cdkeys, psn-store, xbox-store)
- `Product::updateOrCreate()` saves prices
- Delay between scrapers is `usleep(100_000)` (100ms)

## What to build
1. Increase throttling delay between scrapers from 100ms to 300ms
2. Add `price_fetched_at` => `now()` to the `Product::updateOrCreate()` data array
3. Add `price_fetched_at` => `now()` when creating new products
4. In `FetchPricesForGame::handle()`, at the end, update ALL existing products for this game that were NOT updated in this run — set `price_fetched_at = null` to indicate they weren't refreshed (optional but useful for stale detection)
5. Actually, simpler: just set `price_fetched_at` on every product we create/update

## Exact code changes needed

In `app/Jobs/FetchPricesForGame.php`:
- Change `usleep(100_000)` to `usleep(300_000)` (300ms between scrapers)
- In `Product::updateOrCreate()` data array, add: `'price_fetched_at' => now()`
- Also add `'price_fetched_at' => now()` to the `firstOrCreate` store call (no, stores don't need it)
- Keep all existing error handling

## Important constraints
- Do NOT change scraper order or logic
- Do NOT remove any scrapers
- Keep all existing logging
- price_fetched_at must be set for every product we save
- Commit with message: "Task 30B: Add price_fetched_at and increase scraper throttling"
