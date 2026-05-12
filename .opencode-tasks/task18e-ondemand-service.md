# Task: Modify OnDemandSearchService to dispatch FetchPricesForGame job

## Context
GamePrice Laravel 12. When a user searches for a game not in DB, we import it from Steam but don't fetch prices.

## What exists
`app/Services/OnDemandSearchService.php` currently:
1. Searches Steam for the game
2. Creates a new `Game` record
3. Dispatches `FetchSteamGameDetails` job

We need to ALSO dispatch the new `FetchPricesForGame` job after creating the game.

## What to build
Modify `app/Services/OnDemandSearchService.php`:

After this line:
```php
dispatch(new \App\Jobs\FetchSteamGameDetails($game));
```

Add this line:
```php
dispatch(new \App\Jobs\FetchPricesForGame($game));
```

Also add the import at the top of the file:
```php
use App\Jobs\FetchPricesForGame;
```

## Important constraints
- Only add the dispatch and the import. Do NOT change any other logic.
