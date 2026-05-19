# Task: Optimize scrapers for faster execution + revert to queues

## Context
GamePrice Laravel 12. Scraping is too slow when synchronous, causing HTTP timeouts. Need to optimize.

## What exists
- `app/Jobs/FetchPricesForGame.php` — has 500ms sleep between scrapers, Cache::flush() at end
- `app/Services/OnDemandSearchService.php` — currently calls job synchronously (TEMPORARY debug)
- All scrapers in `app/Services/Scrapers/` use 30s timeout

## What to build

### 1. Optimize `app/Jobs/FetchPricesForGame.php`
- Change `usleep(500_000)` to `usleep(200_000)` (200ms instead of 500ms)
- Remove `Cache::flush()` at the end (it's too aggressive; cache invalidation should be per-game)

### 2. Optimize all scraper services
In each scraper in `app/Services/Scrapers/`, change Http timeout from 30 to 10 seconds:
- EnebaScraper.php
- InstantGamingScraper.php
- CheapSharkScraper.php
- G2AScraper.php
- KinguinScraper.php

Change `->timeout(30)` to `->timeout(10)`

### 3. Revert OnDemandSearchService to use queues
In `app/Services/OnDemandSearchService.php`, replace the synchronous call with dispatch:

Replace:
```php
// TEMPORARY: synchronous execution for debugging
$job = new FetchPricesForGame($game);
$job->handle();
```

With:
```php
dispatch(new FetchPricesForGame($game));
```

## Important constraints
- Do NOT change scraping logic, only timeouts and sleep duration
- Keep all try/catch blocks
