# Task 23: Fix refresh button for games with fake prices

## Context
GamePrice Laravel 12 + Vue 3 + Inertia. Many games have fake/estimated prices (`is_real_price = false`) from the old BaseAffiliateService. The refresh prices button only shows when `products.length === 0`, but these games have products — just fake ones.

## What exists
- `resources/js/Pages/GameShow.vue` — has refresh button at lines 453-460 with `v-if="products.length === 0"`
- `app/Http/Controllers/GameController.php` — has `refreshPrices()` method
- `app/Jobs/FetchPricesForGame.php` — scrapes real prices
- `app/Models/Product.php` — has `is_real_price` boolean field

## What to build
1. In `GameShow.vue`:
   - Change button condition from `v-if="products.length === 0"` to `v-if="products.length === 0 || !products.some(p => p.is_real_price)"`
   - Change the main `v-if` for the table from `v-if="products.length > 0 && products.some(p => p.is_real_price)"` to `v-if="products.some(p => p.is_real_price)"` (simpler)
   - Change the `v-else` text from "No hay precios disponibles" to "No hay precios reales disponibles" when products exist but are all fake

2. In `GameController::refreshPrices()`:
   - Before dispatching the job, delete all fake products for this game: `Product::where('game_id', $game->id)->where('is_real_price', false)->delete();`
   - Then dispatch `FetchPricesForGame::dispatchSync($game)`
   - Keep the flash message

3. In `routes/web.php`:
   - Ensure the route `POST /juego/{game}/refresh-prices` exists and is correct

## Important constraints
- Do NOT modify FetchPricesForGame job
- Do NOT break existing functionality for games with real prices
- The button should show for ALL games without real prices (empty OR all fake)
