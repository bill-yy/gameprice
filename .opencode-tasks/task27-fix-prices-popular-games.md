# Task 27: Fix prices for popular games - CheapShark scraper + CSRF refresh button

## Context
GamePrice Laravel 12 + Vue 3 + Inertia. Production site shows "No hay precios reales disponibles" for popular games like Elden Ring, Battlefield 1. The home page DOES show prices for some games (from CheapShark deals import), but popular AAA titles are missing.

## Root causes identified
1. `CheapSharkScraper` uses `/api/1.0/deals?title=` endpoint which often returns EMPTY for AAA games. The `/api/1.0/games?title=` endpoint DOES return results for Elden Ring.
2. The "Actualizar precios" button on game pages returns HTTP 419 (CSRF token mismatch) because the frontend doesn't send the CSRF token correctly.
3. Other HTML scrapers (Eneba, G2A, InstantGaming, Kinguin, CDKeys) are likely blocked by WAF/CAPTCHA in production.

## What exists
- `app/Services/Scrapers/CheapSharkScraper.php` — uses `/deals` endpoint, has `findBestMatch` method
- `app/Jobs/FetchPricesForGame.php` — calls 8 scrapers sequentially with time guard
- `app/Http/Controllers/GameController.php` — `refreshPrices()` method with try-catch
- `resources/js/Pages/GameShow.vue` — shows "No hay precios reales disponibles" + refresh button
- `resources/js/Components/GameCard.vue` — game cards with price display

## What to fix

1. **Fix CheapSharkScraper** (`app/Services/Scrapers/CheapSharkScraper.php`):
   - PRIMARY: Keep the `/deals` endpoint as first attempt
   - FALLBACK: If `/deals` returns empty, try `/api/1.0/games?title={query}&limit=5`
   - The `/games` endpoint returns objects with: `gameID`, `external` (title), `cheapest` (price in USD), `steamAppID`
   - Convert USD to EUR using approximate rate (1 USD ≈ 0.92 EUR) or just return USD as-is with currency='USD'
   - Build deal URL using: `https://www.cheapshark.com/redirect?dealID={cheapestDealID}` (the /games endpoint returns `cheapestDealID`)

2. **Fix CSRF in refresh button** (`resources/js/Pages/GameShow.vue`):
   - Find the "Actualizar precios" button and its click handler
   - The button must send a POST request WITH the Laravel CSRF token
   - In Inertia.js apps, CSRF token is available in the page props or via `document.querySelector('meta[name=csrf-token]')`
   - The request should use Inertia's `router.post()` or axios with proper headers
   - After successful refresh, the page should reload to show new prices

3. **Ensure FetchPricesForGame prioritizes working scrapers**:
   - CheapShark should be FIRST in the scrapers list (before Eneba, G2A, etc.) since it's the most reliable API-based scraper
   - Move `cheapshark` to position 1 in the `$scrapers` array

## Important constraints
- Do NOT break existing working functionality (games that already have prices must keep working)
- CheapShark prices are in USD — either convert to EUR or store as USD with currency='USD'
- The refresh button must work without page errors (no 419, no 500)
- After fixing, a game like Elden Ring should be able to get prices from CheapShark when user clicks "Actualizar precios"
