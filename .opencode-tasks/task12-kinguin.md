# Task: Kinguin Scraper

## Context
This is a Laravel 12 project at /tmp/gameprice-repo.

## What exists
- ScrapeEneba.php, ScrapeGamivo.php, ScrapeG2A.php, ScrapeInstantGaming.php
- Import commands for each
- StoreSeeder with existing stores
- Schedules in routes/console.php

## What to build

### 1. Create app/Console/Commands/ScrapeKinguin.php
- Searches Kinguin for games in our DB
- Extracts: title, current_price, original_price, discount_percent, url, region
- Saves to data/kinguin_prices.json
- Rate limited (1s between requests)
- Signature: kinguin:scrape {--limit=50}

### 2. Create app/Console/Commands/ImportKinguinJson.php
- Imports data/kinguin_prices.json
- Creates products with is_real_price=true + price history
- Signature: kinguin:import-json

### 3. Update routes/console.php
Add schedule: kinguin:scrape daily at 07:30
Add schedule: kinguin:import-json daily at 08:00

### 4. Update docker/entrypoint.sh
Add: php artisan kinguin:import-json

### 5. Update database/seeders/StoreSeeder.php
Add Kinguin store:
```php
['name' => 'Kinguin', 'slug' => 'kinguin', 'logo_url' => 'https://www.kinguin.net/favicon.ico', 'website_url' => 'https://www.kinguin.net', 'is_active' => true, 'is_official' => false, 'rating' => 3.6, 'review_count' => 80000],
```

## Important
- Follow existing patterns exactly
- Rate limit to avoid blocks
- Handle errors gracefully
- Minimal changes to existing files
