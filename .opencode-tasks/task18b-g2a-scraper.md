# Task: Create G2AScraper Service

## Context
GamePrice Laravel 12. Need a reusable scraper service for G2A prices.

## What exists
`app/Console/Commands/ScrapeG2A.php` has these private methods:
- `searchG2A(string $query)` — POST to https://www.g2a.com/search/api/v3/products with phrase, itemsPerPage=24
- `parseProducts(array $data)` — extracts name, price, originalPrice, discount, slug, url, region, inStock
- `findBestMatch(array $results, string $gameTitle)` — sorts by title similarity + Steam/PC bonus + global region bonus

## What to build
Create `app/Services/Scrapers/G2AScraper.php` with ONE public method:
```php
public function search(string $query): ?array
```
Returns: `['name', 'price_eur', 'original_price_eur', 'discount_percent', 'url', 'region', 'in_stock']` or null.

Copy the exact logic from ScrapeG2A command into this service class. Use `Illuminate\Support\Facades\Http` and `Illuminate\Support\Facades\Log`.

Wrap everything in try/catch, return null on any error.
