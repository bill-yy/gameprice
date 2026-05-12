# Task: Gamesplanet Scraper

## Context
This is a Laravel 12 project at /tmp/gameprice-repo.

## What exists
- 6 scrapers already (Eneba, Gamivo, G2A, Instant Gaming, Kinguin, CheapShark)
- Import commands for each
- StoreSeeder with existing stores
- Schedules in routes/console.php

## What to build

### 1. Create app/Console/Commands/ScrapeGamesplanet.php
- Gamesplanet has a public API or structured data
- Try: https://us.gamesplanet.com/api/products/search?q=baldurs+gate+3
- Or scrape HTML from https://us.gamesplanet.com/search?query=baldurs+gate+3
- Extract: title, current_price, original_price, discount_percent, url, region
- Saves to data/gamesplanet_prices.json
- Rate limited (1s between requests)
- Signature: gamesplanet:scrape {--limit=50}

### 2. Create app/Console/Commands/ImportGamesplanetJson.php
- Imports data/gamesplanet_prices.json
- Creates products with is_real_price=true + price history
- Signature: gamesplanet:import-json

### 3. Update routes/console.php
Add schedule: gamesplanet:scrape daily at 08:30
Add schedule: gamesplanet:import-json daily at 09:00

### 4. Update docker/entrypoint.sh
Add: php artisan gamesplanet:import-json

### 5. Update database/seeders/StoreSeeder.php
Add Gamesplanet store:
```php
['name' => 'Gamesplanet', 'slug' => 'gamesplanet', 'logo_url' => 'https://www.gamesplanet.com/favicon.ico', 'website_url' => 'https://www.gamesplanet.com', 'is_active' => true, 'is_official' => true, 'rating' => 4.5, 'review_count' => 200000],
```

## Important
- Follow existing patterns exactly
- Rate limit to avoid blocks
- Handle errors gracefully
- Minimal changes to existing files
