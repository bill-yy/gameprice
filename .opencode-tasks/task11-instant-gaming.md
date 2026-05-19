# Task: Instant Gaming Scraper

## Context
This is a Laravel 12 project at /tmp/gameprice-repo.

## What exists
- ScrapeEneba.php (Apollo GraphQL)
- ScrapeGamivo.php (JSON-LD/__NEXT_DATA__/HTML)
- ScrapeG2A.php (API)
- ImportEnebaJson.php, ImportGamivoJson.php, ImportG2AJson.php
- StoreSeeder with existing stores

## What to build

### 1. Investigate Instant Gaming
Use webfetch to check https://www.instant-gaming.com/en/search/?query=baldurs-gate-3
Look for:
- JSON-LD structured data
- Any embedded JSON (window.__INITIAL_STATE__, etc.)
- HTML patterns for prices
- API endpoints

### 2. Create app/Console/Commands/ScrapeInstantGaming.php
Scraper that:
- Fetches Instant Gaming search results
- Extracts: title, current_price, original_price, discount_percent, url
- Saves to data/instantgaming_prices.json
- Rate limited (1s between requests)
- Signature: instantgaming:scrape {--limit=50}

### 3. Create app/Console/Commands/ImportInstantGamingJson.php
- Reads data/instantgaming_prices.json
- Creates/updates products with is_real_price=true
- Creates price history
- Signature: instantgaming:import-json

### 4. Update routes/console.php
Add schedule: instantgaming:scrape daily at 06:30
Add schedule: instantgaming:import-json daily at 07:00

### 5. Update docker/entrypoint.sh
Add: php artisan instantgaming:import-json

### 6. Update database/seeders/StoreSeeder.php
Add Instant Gaming store:
```php
['name' => 'Instant Gaming', 'slug' => 'instant-gaming', 'logo_url' => 'https://www.instant-gaming.com/favicon.ico', 'website_url' => 'https://www.instant-gaming.com', 'is_active' => true, 'is_official' => false, 'rating' => 4.2, 'review_count' => 120000],
```

## Important
- Follow existing patterns
- Rate limit to avoid blocks
- Handle errors gracefully
- Minimal changes to existing files
