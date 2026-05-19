# Task 20: Create standalone Scraping API Service (RapidAPI ready)

## Context
GamePrice has scraping services in `app/Services/Scrapers/`. We want to extract this into a separate monetizable API service.

## What exists
- `app/Services/Scrapers/EnebaScraper.php`
- `app/Services/Scrapers/InstantGamingScraper.php`
- `app/Services/Scrapers/CheapSharkScraper.php`
- `app/Services/Scrapers/G2AScraper.php`
- `app/Services/Scrapers/KinguinScraper.php`
- Each has `search(string $query): ?array` and returns `[[name, price, url, store]]`

## What to build
Create a new Laravel project structure inside `/api-service/` directory with:

1. **Routes** (`routes/api.php`):
   - `GET /api/v1/search?q={game_name}` — search all stores, return aggregated results
   - `GET /api/v1/prices/{store}?q={game_name}` — search specific store
   - `GET /api/v1/stores` — list available stores
   - `GET /api/v1/deals` — return best deals across all stores
   
2. **Controllers**:
   - `app/Http/Controllers/Api/V1/SearchController.php`
   - `app/Http/Controllers/Api/V1/StoreController.php`
   
3. **Middleware**:
   - API rate limiting (100 requests/hour for free tier)
   - API key authentication (simple header `X-API-Key`)
   
4. **Copy ALL scraper services** from main app to `api-service/app/Services/Scrapers/`

5. **Response format** (JSON):
```json
{
  "success": true,
  "query": "elden ring",
  "results": [
    {"store": "Instant Gaming", "name": "Elden Ring", "price": 35.99, "currency": "EUR", "url": "...", "platform": "PC"}
  ],
  "meta": {"count": 1, "stores_searched": 5}
}
```

6. **Dockerfile** for the API service (simple PHP-FPM + nginx)

## Important constraints
- This is a NEW directory `/api-service/` — do NOT modify existing GamePrice code
- Use Laravel 12 if possible, or Laravel 11
- Keep it lightweight — no frontend, no Inertia, pure API
- Add `README.md` with RapidAPI monetization setup instructions
