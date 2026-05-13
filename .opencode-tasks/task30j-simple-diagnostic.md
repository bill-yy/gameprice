# Task 30J: Simplify diagnostic endpoint

## Context
GamePrice production. The `/debug/scrapers/{game:slug}` endpoint returns HTTP 500 because executing all scrapers synchronously takes too long or causes fatal errors. Need a simpler endpoint that just shows database state without running scrapers.

## What exists
`app/Http/Controllers/DebugScraperController.php` with a `diagnose` method that runs all 8 scrapers synchronously.

## What to build
Replace the `diagnose` method to:
1. Load the game by slug
2. Return JSON with:
   - game info (id, title, slug)
   - count of existing products for this game
   - list of existing products with store name, platform, price, is_real_price, price_fetched_at
   - the scrapers list (just names, don't execute them)
   - queue connection info from config (just `config('queue.default')`)
   - count of pending jobs in the `jobs` table (if database queue)

Do NOT execute any scrapers. This endpoint should respond instantly.

## Important constraints
- Keep the same route `/debug/scrapers/{game:slug}`
- Do NOT execute scrapers or external HTTP requests
- Commit: "Task 30J: Simplify diagnostic endpoint to DB-only"
