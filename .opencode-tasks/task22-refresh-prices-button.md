# Task 22: Add "Refresh Prices" button to game pages

## Context
GamePrice Laravel 12 + Vue 3 + Inertia. Some games were imported before the scraping fix and have no prices. Users need a way to manually trigger price scraping for any game.

## What exists
- `app/Jobs/FetchPricesForGame.php` — job that scrapes all stores for a game
- `app/Http/Controllers/GameController.php` — has `show()` method
- `resources/js/Pages/GameShow.vue` — displays game details and price table
- `routes/web.php` — game routes

## What to build
1. Add a new route `POST /juego/{slug}/refresh-prices` in `routes/web.php`
2. Add method `refreshPrices(string $slug)` in `GameController` that:
   - Finds the game by slug
   - Executes `FetchPricesForGame::dispatchSync($game)`
   - Returns redirect back with success message "Precios actualizados"
3. In `GameShow.vue`, add a button "🔄 Actualizar precios" that:
   - Only shows when the game has 0 products
   - Sends POST request to the refresh route
   - Uses Inertia `router.post()`
   - Shows loading state while scraping
4. Add CSRF token handling (Laravel default)

## Important constraints
- Do NOT break existing functionality
- Only show button when `products.length === 0`
- Use existing `FetchPricesForGame` job — do NOT modify it
- Keep the UI simple — button below "No hay precios" message
