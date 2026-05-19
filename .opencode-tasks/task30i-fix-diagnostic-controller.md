# Task 30I: Fix DebugScraperController null store bug

## Context
GamePrice production. The `/debug/scrapers/{game:slug}` endpoint returns HTTP 500. The issue is in `app/Http/Controllers/DebugScraperController.php` line 69-74:
```php
$existingProducts = Product::where('game_id', $game->id)
    ->with('store:id,name,slug')
    ->get()
    ->map(fn ($p) => [
        'store' => $p->store->name ?? $p->store->slug,
```
When `$p->store` is null (orphaned product or store deleted), `$p->store->name` throws `Error: Attempt to read property "name" on null`.

## What exists
`app/Http/Controllers/DebugScraperController.php` with the diagnose method.

## What to build
Fix line 69-74 to safely handle null store:
```php
$existingProducts = Product::where('game_id', $game->id)
    ->with('store:id,name,slug')
    ->get()
    ->map(fn ($p) => [
        'store' => $p->store?->name ?? $p->store?->slug ?? 'Unknown',
        'platform' => $p->platform,
        'price' => $p->current_price,
        'url' => $p->url,
        'price_fetched_at' => $p->price_fetched_at?->toIso8601String(),
    ]);
```

Also add `use App\Models\Product;` import if missing (should already be there).

## Important constraints
- Only modify the null-safe access in the map callback
- Commit: "Task 30I: Fix null store access in diagnostic controller"
