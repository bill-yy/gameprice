# Task 18: Scrapeo On-Demand al Importar Juegos Nuevos

## Context
GamePrice is a Laravel 12 + Vue 3 + Inertia.js price comparison site.

Current problem: When a user searches for a game not in DB (e.g. "Crimson Desert"), the `OnDemandSearchService` imports the game from Steam but does NOT fetch prices. The game page shows "No hay precios disponibles".

## What exists
- `app/Services/OnDemandSearchService.php` — imports games from Steam, dispatches `FetchSteamGameDetails` job
- `app/Console/Commands/ScrapeEneba.php` — REAL scraper, saves to DB
- `app/Console/Commands/ScrapeCheapShark.php` — REAL API, saves to DB
- `app/Console/Commands/ScrapeInstantGaming.php` — REAL scraper but saves to JSON only
- `app/Console/Commands/ScrapeG2A.php` — REAL scraper but saves to JSON only
- `app/Console/Commands/ScrapeKinguin.php` — REAL scraper but saves to JSON only
- `app/Models/Game.php` and `app/Models/Product.php` — already exist
- `app/Models/Store.php` — already exists

## What to build (Fase 1 only)

### 1. Create `app/Jobs/FetchPricesForGame.php`
A queued job that, given a Game, runs ALL real scrapers and saves prices to DB:
- Eneba (use logic from ScrapeEneba command — searchEneba + findBestMatch + save Product)
- Instant Gaming (use logic from ScrapeInstantGaming command)
- CheapShark (use logic from ScrapeCheapShark — search by title via API)
- G2A (use logic from ScrapeG2A command)
- Kinguin (use logic from ScrapeKinguin command)

Each scraper should:
- Search the store using the game title
- Find best match
- Save/update Product in DB (NOT JSON)
- Set `is_real_price = true`
- Use `Store::firstOrCreate` for each store
- Apply rate limiting (sleep between requests)
- Catch and log errors (don't fail the whole job if one store fails)

### 2. Modify `app/Services/OnDemandSearchService.php`
After creating the game and dispatching `FetchSteamGameDetails`, ALSO dispatch the new `FetchPricesForGame` job.

### 3. Fix `ScrapeInstantGaming`, `ScrapeG2A`, `ScrapeKinguin`
These currently save to JSON files. Modify them to ALSO save to DB (use Product::updateOrCreate), keeping the JSON export as backup.

### 4. Add queue worker to docker/supervisor config
Ensure the queue worker is running so jobs actually execute.

## Important constraints
- Must save prices to DB (Products table), not just JSON
- Must handle errors gracefully (one store failing shouldn't break others)
- Must use queues (dispatch, not synchronous) so the web request doesn't hang
- Must keep existing behavior of ScrapeEneba and ScrapeCheapShark commands
- The user is in Spain (EUR currency, ES region preference)
