# Task: Gamivo Scraper

## Context
This is a Laravel 12 project at /tmp/gameprice-repo.

## What exists
- app/Console/Commands/ScrapeEneba.php - Eneba scraper using Apollo GraphQL from window.__APOLLO_STATE__
- app/Console/Commands/ImportEnebaJson.php - Imports eneba_prices.json to DB
- app/Console/Commands/ScrapeAllPrices.php - Fake price generator (respects is_real_price)
- Stores are seeded in database/seeders/StoreSeeder.php

## What to build

### 1. Investigate Gamivo
Use webfetch to check https://www.gamivo.com/search?q=baldurs-gate-3
Look for:
- Apollo GraphQL state in HTML (window.__APOLLO_STATE__ or similar)
- JSON-LD structured data
- Any embedded JSON with product prices
- If no structured data, look at the page source for price data patterns

### 2. Create app/Console/Commands/ScrapeGamivo.php
Based on the investigation, create a scraper that:
- Searches for games in our database that don't have Gamivo prices yet
- For each game, fetches the Gamivo search/product page
- Extracts: current_price, original_price, discount_percent, url
- Saves results to data/gamivo_prices.json
- Uses rate limiting (0.5s between requests)
- Has signature: gamivo:scrape {--limit=50}

### 3. Create app/Console/Commands/ImportGamivoJson.php
Similar to ImportEnebaJson.php:
- Reads data/gamivo_prices.json
- Finds or creates Game by title matching
- Creates/updates Product for Gamivo store with is_real_price=true
- Has signature: gamivo:import-json

### 4. Update routes/console.php
Add schedule: prices:scrape-gamivo daily at 04:30
Add schedule: prices:import-gamivo-json daily at 05:00

### 5. Update docker/entrypoint.sh
Add: php artisan prices:import-gamivo-json

### 6. Update StoreSeeder.php
If Gamivo store doesn't exist, add it:
```php
['name' => 'Gamivo', 'slug' => 'gamivo', 'logo_url' => 'https://www.gamivo.com/favicon.ico', 'website_url' => 'https://www.gamivo.com', 'is_active' => true, 'is_official' => false],
```
Use updateOrCreate to avoid duplicates.

## Important
- Follow the same patterns as ScrapeEneba.php and ImportEnebaJson.php
- Rate limit to avoid being blocked
- Handle errors gracefully
- Minimal changes to existing files
