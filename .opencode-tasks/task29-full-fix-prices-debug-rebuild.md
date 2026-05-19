# Task 29: Complete fix for prices (debug 500, rebuild assets, verify end-to-end)

## Context
GamePrice Laravel 12 + Vue 3 + Inertia + Docker + Dokploy.
Production: https://baratoya.billytech.es

Current issues:
1. POST /juego/{game}/refresh-prices returns HTTP 500 for ALL games
2. Frontend JS assets are NOT rebuilding (same hash after push)  
3. Some popular games show "No hay precios disponibles" (Battlefield 1)
4. CSRF meta tag added but Axios may not be picking it up from compiled JS

## What exists
- `app/Http/Controllers/GameController.php` — `refreshPrices()` executes `$job->handle()` directly
- `app/Jobs/FetchPricesForGame.php` — 8 scrapers with individual try-catch
- `docker/Dockerfile` — builds assets with `npm run build`, uses CACHE_BUST arg
- `resources/js/bootstrap.js` — now has CSRF token config (but NOT in compiled JS in prod)
- `resources/views/app.blade.php` — has csrf-token meta tag
- `routes/web.php` — `Route::post('/juego/{game}/refresh-prices', ...)`

## What to do

### Step 1: Debug the 500 error
Add EXTENSIVE logging to identify which scraper/line causes the 500:

In `FetchPricesForGame.php`:
- Log BEFORE and AFTER each scraper execution
- Log the exact exception message and class when a scraper fails
- Log the game title being searched
- Wrap the ENTIRE foreach loop in a try-catch that logs any fatal error

In `GameController::refreshPrices()`:
- Log the game ID and title at the start
- Log any exception from the job with full trace
- Return JSON response with error details when debug is enabled (check `config('app.debug')`)

### Step 2: Create a minimal test route
Add a GET route `/test-scraper/{game}` that:
- Only runs the CheapShark scraper (most reliable API)
- Returns JSON with the result or error
- Has NO CSRF requirement (GET request)
- This lets us test the scraper independently of the frontend

Route: `Route::get('/test-scraper/{game}', [GameController::class, 'testScraper']);`

### Step 3: Fix asset rebuilding in Dockerfile
The JS assets are NOT updating in production. Fix the Dockerfile cache invalidation:

The current issue: Docker layer caching means `COPY . .` may not trigger rebuild if the layer cache hits.

Fix: Make the build layer dependent on something that changes every push:
- Add a `BUILD_ID` ARG that is used in the build
- OR copy a file that changes (like package.json) BEFORE npm run build
- OR add `ARG CACHE_BUST` and use it in a RUN command that touches a file before build

The key is: `npm run build` MUST execute every time the code changes.

Also verify: the `COPY . .` happens BEFORE `npm run build`, and the build outputs to `public/build/` which is served by nginx.

### Step 4: Fix Axios CSRF in a way that doesn't need rebuild
Since compiled JS is stale, add CSRF handling inline in the HTML:

In `resources/views/app.blade.php`, AFTER the @vite scripts, add an inline script:
```html
<script>
    if (window.axios && document.querySelector('meta[name="csrf-token"]')) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = 
            document.querySelector('meta[name="csrf-token"]').content;
    }
</script>
```
This runs on every page load and configures axios dynamically, bypassing the stale compiled JS.

### Step 5: Make refreshPrices return JSON for AJAX
The current `refreshPrices()` returns `back()->with('success', ...)` which is for regular form posts.
When called via Inertia/AJAX, it should return a proper response.

Modify `refreshPrices()` to detect AJAX/Inertia requests:
```php
if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
    return response()->json(['success' => true, 'message' => 'Precios actualizados']);
}
return back()->with('success', 'Precios actualizados');
```

### Step 6: Add fallback USD to EUR conversion
CheapShark returns prices in USD. Add conversion:
- In `CheapSharkScraper`, add approximate conversion: `price_eur = usd_price * 0.92`
- Or add a helper method `usdToEur(float $usd): float`

### Important constraints
- Do NOT break existing games that already show prices
- The test route must be safe and read-only (no DB writes unless explicitly needed)
- After fixing, Battlefield 1 should be able to get prices from CheapShark
- The inline CSRF script must work immediately without waiting for JS rebuild
