# Task: Use dispatchSync for immediate job execution

## Context
GamePrice Laravel 12. The FetchPricesForGame job is dispatched to queue but never executes (queue worker issue).

## What exists
`app/Services/OnDemandSearchService.php` currently has:
```php
dispatch(new FetchPricesForGame($game));
```

## What to build
Change to `dispatchSync` so the job runs immediately in the same HTTP request:

```php
dispatchSync(new FetchPricesForGame($game));
```

This executes the job synchronously but still uses the job class structure. Errors are caught inside the job's try/catch blocks so they won't break the request.

## Important constraints
- Only modify OnDemandSearchService.php
- Change `dispatch(new FetchPricesForGame($game))` to `dispatchSync(new FetchPricesForGame($game))`
