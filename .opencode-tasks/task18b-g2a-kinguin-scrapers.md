# Task 18B: Create G2A and Kinguin Scraper Services

## Context
GamePrice Laravel 12. We already have Eneba, InstantGaming, and CheapShark scrapers in `app/Services/Scrapers/`.

## What exists
- `app/Console/Commands/ScrapeG2A.php` — has `searchG2A()` (POST to g2a.com/search/api/v3/products), `parseProducts()`, `findBestMatch()`
- `app/Console/Commands/ScrapeKinguin.php` — has `searchKinguin()` (GET to kinguin.net/svc/search/api/v1/products), `parseProducts()`, `findBestMatch()`

## What to build

### 1. Create `app/Services/Scrapers/G2AScraper.php`
Service class with public method:
```php
public function search(string $query): ?array
```
Returns: `['name', 'price_eur', 'original_price_eur', 'discount_percent', 'url', 'region', 'in_stock']` or null.

Copy logic from ScrapeG2A command. Use Http::withHeaders([...])->post(). Add try/catch + Log::warning.

### 2. Create `app/Services/Scrapers/KinguinScraper.php`
Same pattern. Copy logic from ScrapeKinguin command. Use Http::withHeaders([...])->get().

## Important constraints
- Namespace: `App\Services\Scrapers`
- Must catch all exceptions and return null on failure
- Do NOT modify existing files
- Do NOT create the directory (it already exists)
