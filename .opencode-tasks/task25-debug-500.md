# Task 25: Debug and fix 500 error in refreshPrices

## Context
GamePrice Laravel 12. The `POST /juego/{slug}/refresh-prices` endpoint returns 500 Server Error. The method uses dispatchSync to run FetchPricesForGame which calls 8 scrapers.

## What exists
- `app/Http/Controllers/GameController.php` — `refreshPrices()` with try-catch (Task 24)
- `app/Jobs/FetchPricesForGame.php` — calls 8 scrapers sequentially with 200ms delays
- New scrapers: CDKeysScraper, PSNStoreScraper, XboxStoreScraper

## What to investigate and fix
1. Check if the issue is caused by new scrapers failing. Temporarily comment out or skip the 3 new scrapers (cdkeys, psn-store, xbox-store) in FetchPricesForGame to test if the 5 original scrapers work.

2. If the 5 original scrapers work, then the issue is in one of the new scrapers. Add individual try-catch with logging around each scraper call in FetchPricesForGame so failures don't crash the whole job.

3. Alternatively, the issue might be that `dispatchSync` inside a web request times out after 30s (8 scrapers * ~3s each with delays = ~24s). Consider:
   - Reducing delays further (100ms)
   - Running scrapers in parallel if possible
   - Or making the endpoint return immediately and use a polling/status endpoint

## Important constraints
- Do NOT break existing working functionality
- The job must complete successfully for games that DO have PC prices (like Resident Evil 4)
- Add proper error isolation so one failing scraper doesn't break others
