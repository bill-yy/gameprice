# Task: Update ScrapeInstantGaming, ScrapeG2A, ScrapeKinguin Commands to save to DB

## Context
GamePrice Laravel 12. We have new scraper services in `app/Services/Scrapers/`.

## Problem
These commands currently save results to JSON files only:
- `ScrapeInstantGaming` → `data/instantgaming_prices.json`
- `ScrapeG2A` → `data/g2a_prices.json`
- `ScrapeKinguin` → `data/kinguin_prices.json`

They should ALSO save to the database (Products table).

## What exists
- `app/Services/Scrapers/InstantGamingScraper.php` — `search(string $query): ?array`
- `app/Services/Scrapers/G2AScraper.php` — `search(string $query): ?array`
- `app/Services/Scrapers/KinguinScraper.php` — `search(string $query): ?array`
- `App\Models\Product` — `updateOrCreate()` works with `game_id`, `store_id`
- `App\Models\Store` — `firstOrCreate()` works with `slug`

## What to build

### Modify `app/Console/Commands/ScrapeInstantGaming.php`
In the `handle()` method, after collecting `$results`, ALSO save each result to DB:

```php
$store = Store::firstOrCreate(
    ['slug' => 'instant-gaming'],
    ['name' => 'Instant Gaming', 'is_active' => true, 'is_official' => false]
);

foreach ($results as $result) {
    $game = Game::where('title', $result['game_title'])->first();
    if ($game) {
        Product::updateOrCreate(
            ['game_id' => $game->id, 'store_id' => $store->id],
            [
                'current_price' => $result['price_eur'],
                'original_price' => $result['original_price_eur'],
                'discount_percent' => $result['discount_percent'],
                'url' => $result['url'],
                'affiliate_url' => $result['url'],
                'is_real_price' => true,
                'currency' => 'EUR',
                'platform' => 'PC',
                'region' => 'global',
                'type' => 'key',
                'in_stock' => $result['in_stock'],
            ]
        );
    }
}
Cache::flush();
```

### Modify `app/Console/Commands/ScrapeG2A.php`
Same pattern. Store slug = `'g2a'`, name = `'G2A'`.

### Modify `app/Console/Commands/ScrapeKinguin.php`
Same pattern. Store slug = `'kinguin'`, name = `'Kinguin'`.

## Important constraints
- Keep the JSON export as backup (don't remove existing JSON saving)
- Add `use App\Models\Product;` and `use App\Models\Store;` and `use Illuminate\Support\Facades\Cache;` if not already present
- Only modify the `handle()` method, don't touch the scraping logic
