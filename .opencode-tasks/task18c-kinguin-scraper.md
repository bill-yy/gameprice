# Task: Create KinguinScraper Service

## Context
GamePrice Laravel 12. Need a reusable scraper service for Kinguin prices.

## What exists
`app/Console/Commands/ScrapeKinguin.php` has these private methods:
- `searchKinguin(string $query)` — GET to https://www.kinguin.net/svc/search/api/v1/products?q={query}&limit=24&sort=score
- `parseProducts(array $data)` — extracts name, price, originalPrice, discount, slug, url, region, inStock
- `findBestMatch(array $results, string $gameTitle)` — sorts by title similarity + Steam/PC bonus + global region bonus

## What to build
Create `app/Services/Scrapers/KinguinScraper.php` with ONE public method:
```php
public function search(string $query): ?array
```
Returns: `['name', 'price_eur', 'original_price_eur', 'discount_percent', 'url', 'region', 'in_stock']` or null.

Copy the exact logic from ScrapeKinguin command. Use `Illuminate\Support\Facades\Http` and `Illuminate\Support\Facades\Log`.

Wrap everything in try/catch, return null on any error.
