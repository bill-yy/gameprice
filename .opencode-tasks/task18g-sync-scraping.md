# Task: Make FetchPricesForGame execute synchronously (temporary debug)

## Context
GamePrice Laravel 12. The `FetchPricesForGame` job is dispatched but prices never appear. Need to diagnose if the job is failing.

## What exists
- `app/Services/OnDemandSearchService.php` — dispatches `FetchPricesForGame` job after creating a game
- `app/Jobs/FetchPricesForGame.php` — queued job that scrapes prices

## What to build
Temporarily modify `app/Services/OnDemandSearchService.php` to execute the scraping SYNCHRONOUSLY instead of dispatching a job. This way we can see any errors immediately in the HTTP response.

Replace these lines:
```php
dispatch(new \App\Jobs\FetchSteamGameDetails($game));
dispatch(new FetchPricesForGame($game));
```

With:
```php
dispatch(new \App\Jobs\FetchSteamGameDetails($game));

// TEMPORARY: synchronous execution for debugging
$job = new FetchPricesForGame($game);
$job->handle();
```

This is TEMPORARY for debugging only. We will revert to queue once confirmed working.

## Important constraints
- ONLY modify OnDemandSearchService.php
- Do NOT modify FetchPricesForGame.php
