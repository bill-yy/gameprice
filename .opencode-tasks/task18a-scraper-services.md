# Task 18A: Create Scraper Services (Eneba + InstantGaming)

## Context
GamePrice is Laravel 12. We need reusable scraper services to fetch real game prices.

## What exists
- `app/Console/Commands/ScrapeEneba.php` — has `searchEneba()`, `findBestMatch()`, `resolveMoney()`, `buildAffiliateUrl()` methods
- `app/Console/Commands/ScrapeInstantGaming.php` — has `searchInstantGaming()`, `extractFromSearchResults()`, `extractFromHtml()`, `findBestMatch()` methods

## What to build

### 1. Create `app/Services/Scrapers/EnebaScraper.php`
A service class with ONE public method:
```php
public function search(string $query): ?array
```
Returns: `['name', 'price_eur', 'original_price_eur', 'discount_percent', 'url', 'in_stock']` or null.

Copy the logic from ScrapeEneba command (searchEneba, findBestMatch, resolveMoney, buildAffiliateUrl). Use Http facade. Add proper error handling with try/catch and Log::warning.

### 2. Create `app/Services/Scrapers/InstantGamingScraper.php`
Same pattern. Copy logic from ScrapeInstantGaming command.

### 3. Create `app/Services/Scrapers/CheapSharkScraper.php`
Use the CheapShark API (https://www.cheapshark.com/api/1.0/deals?title={query}&pageSize=5&sortBy=Price).
Find best match by title similarity. Return same array structure.

## Important constraints
- Each class goes in `app/Services/Scrapers/` namespace
- Methods must be public
- Must catch all exceptions and return null on failure
- Use `Illuminate\Support\Facades\Http` and `Illuminate\Support\Facades\Log`
- Do NOT modify any existing files
