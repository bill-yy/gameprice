# Task: Price History Tracking + Chart

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- PriceHistory model exists (app/Models/PriceHistory.php) with fields: product_id, price, currency, recorded_at
- Migration exists for price_history table
- GameShow.vue shows game details and product prices

## What to build

### 1. Update app/Console/Commands/ImportCheapSharkJson.php
After updating/creating a Product, also create a PriceHistory entry IF the price changed.
Add this logic at the end of the product update block:
```php
// Track price history
$latestHistory = \App\Models\PriceHistory::where('product_id', $product->id)
    ->orderByDesc('recorded_at')
    ->first();

if (!$latestHistory || $latestHistory->price != $product->current_price) {
    \App\Models\PriceHistory::create([
        'product_id' => $product->id,
        'price' => $product->current_price,
        'currency' => $product->currency,
        'recorded_at' => now(),
    ]);
}
```

### 2. Update app/Console/Commands/ImportEnebaJson.php
Add the SAME price history tracking logic after updating a product.

### 3. Update app/Http/Controllers/GameController.php
In the show() method, after loading products, also load price history for each product:
```php
'priceHistories' => $game->products->mapWithKeys(fn($p) => [
    $p->id => $p->priceHistories()
        ->where('recorded_at', '>=', now()->subMonths(6))
        ->orderBy('recorded_at')
        ->get(['price', 'recorded_at'])
        ->toArray()
])->toArray(),
```
Pass this to the Inertia render as 'priceHistories'.

### 4. Update resources/js/Pages/GameShow.vue
- Add a new section below the price table: "📈 Historial de precios"
- Use a simple SVG line chart (no external library needed) to show price history per store
- The chart should show the last 6 months of price data
- Show one line per store that has history data
- Use different colors for each store line
- Show dates on X axis and price on Y axis
- Make it responsive and dark-themed matching the site
- If no history data exists, show "No hay datos históricos disponibles"

### 5. Update PriceHistory model if needed
Add the relation to Product if missing (check first).

## Important
- Do NOT change existing functionality
- Keep the dark theme (bg-gray-900, text-white)
- All text in Spanish
- Use existing code patterns
- Only modify the files mentioned above
