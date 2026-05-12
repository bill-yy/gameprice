# Task: G2A Scraper

## Context
This is a Laravel 12 project at /tmp/gameprice-repo.

## What exists
- app/Console/Commands/ScrapeEneba.php - Eneba scraper using Apollo GraphQL
- app/Console/Commands/ScrapeGamivo.php - Gamivo scraper with JSON-LD/__NEXT_DATA__/HTML parsing
- app/Console/Commands/ImportEnebaJson.php / ImportGamivoJson.php - Import JSON to DB
- Stores are seeded in database/seeders/StoreSeeder.php
- G2A offers a public product search API: https://www.g2a.com/search/api/v3/products (POST with JSON body)

## What to build

### 1. Investigate G2A API
Use webfetch to check if G2A has a search API or structured data:
- Try https://www.g2a.com/category/games-c189 (browse page)
- Try POST to https://www.g2a.com/search/api/v3/products with body:
  ```json
  {"itemsPerPage":24,"include":"categories,categoryTree,media,regions,attributes,developerName,publisherName,discount","updatedAfter":null,"sort":"score","isWholesale":false,"funnel":"r","phrase":"baldurs gate 3"}
  ```
- Check response format and extract price data

### 2. Create app/Console/Commands/ScrapeG2A.php
Scraper that:
- Queries the G2A API (or parses HTML if API fails)
- For each game in our DB, searches G2A and extracts:
  - current_price, original_price, discount_percent, url, region
- Saves results to data/g2a_prices.json
- Rate limited (1s between requests)
- Signature: g2a:scrape {--limit=50}

### 3. Create app/Console/Commands/ImportG2AJson.php
- Reads data/g2a_prices.json
- Finds or creates Game by title matching
- Creates/updates Product for G2A store with is_real_price=true
- Creates price history entries
- Signature: g2a:import-json

### 4. Update routes/console.php
Add schedule: g2a:scrape daily at 05:30
Add schedule: g2a:import-json daily at 06:00

### 5. Update docker/entrypoint.sh
Add: php artisan g2a:import-json

### 6. Update database/seeders/StoreSeeder.php
Add G2A store with updateOrCreate:
```php
['name' => 'G2A', 'slug' => 'g2a', 'logo_url' => 'https://www.g2a.com/favicon.ico', 'website_url' => 'https://www.g2a.com', 'is_active' => true, 'is_official' => false],
```

## Important
- Prefer API over scraping when possible
- If G2A API requires headers (User-Agent, Referer), include them
- Handle rate limiting and errors gracefully
- Minimal changes to existing files
