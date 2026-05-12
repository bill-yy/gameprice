# Task: Create FetchPricesForGame Job

## Context
GamePrice Laravel 12. We have 5 scraper services in `app/Services/Scrapers/`:
- `EnebaScraper` — `search(string $query): ?array`
- `InstantGamingScraper` — `search(string $query): ?array`
- `CheapSharkScraper` — `search(string $query): ?array`
- `G2AScraper` — `search(string $query): ?array`
- `KinguinScraper` — `search(string $query): ?array`

Each returns: `['name', 'price_eur', 'original_price_eur', 'discount_percent', 'url', 'in_stock']` or null.
Some also return `'region'` (G2A, Kinguin).

## Models
- `App\Models\Game` — has `id`, `title`
- `App\Models\Product` — fillable: `game_id`, `store_id`, `type`, `platform`, `region`, `url`, `affiliate_url`, `current_price`, `original_price`, `discount_percent`, `is_real_price`, `currency`, `in_stock`
- `App\Models\Store` — has `id`, `slug`, `name`

## What to build
Create `app/Jobs/FetchPricesForGame.php` implementing `Illuminate\Contracts\Queue\ShouldQueue`.

### Constructor
```php
public function __construct(public Game $game) {}
```

### handle() logic
1. Define a map of store slugs to scraper class names:
   ```php
   $scrapers = [
       'eneba' => EnebaScraper::class,
       'instant-gaming' => InstantGamingScraper::class,
       'cheapshark' => CheapSharkScraper::class,
       'g2a' => G2AScraper::class,
       'kinguin' => KinguinScraper::class,
   ];
   ```

2. For each scraper:
   - Instantiate the scraper class
   - Call `$scraper->search($this->game->title)`
   - If result is not null:
     - Get or create Store: `Store::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_active' => true])`
     - Create or update Product:
       ```php
       Product::updateOrCreate(
           ['game_id' => $this->game->id, 'store_id' => $store->id],
           [
               'current_price' => $result['price_eur'],
               'original_price' => $result['original_price_eur'],
               'discount_percent' => $result['discount_percent'],
               'url' => $result['url'],
               'affiliate_url' => $result['url'],
               'is_real_price' => true,
               'currency' => 'EUR',
               'platform' => 'PC',
               'region' => $result['region'] ?? 'global',
               'type' => 'key',
               'in_stock' => $result['in_stock'] ?? true,
           ]
       );
       ```
   - Sleep 500ms between scrapers (rate limiting)
   - Catch any Throwable, Log::warning, continue with next scraper

3. After all scrapers, clear cache: `Cache::flush()`

## Important constraints
- Must use `ShouldQueue` + `Queueable` + `SerializesModels`
- Must NOT fail the whole job if one scraper fails
- Must NOT modify existing files
