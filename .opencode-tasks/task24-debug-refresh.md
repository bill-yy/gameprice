# Task 24: Add error debugging to refreshPrices

## Context
GamePrice Laravel 12. The `refreshPrices` method in GameController returns 500 Server Error. Need to see the actual error message.

## What exists
`app/Http/Controllers/GameController.php` has `refreshPrices()` at lines 246-255:
```php
public function refreshPrices(Game $game)
{
    Product::where('game_id', $game->id)->where('is_real_price', false)->delete();
    FetchPricesForGame::dispatchSync($game);
    Cache::forget("games.show.{$game->slug}");
    return back()->with('success', 'Precios actualizados');
}
```

## What to build
Wrap the method body in a try-catch that catches `\Throwable` and returns back with the error message:
```php
public function refreshPrices(Game $game)
{
    try {
        Product::where('game_id', $game->id)->where('is_real_price', false)->delete();
        FetchPricesForGame::dispatchSync($game);
        Cache::forget("games.show.{$game->slug}");
        return back()->with('success', 'Precios actualizados');
    } catch (\Throwable $e) {
        return back()->with('error', 'Error: ' . $e->getMessage());
    }
}
```

Also, in `GameShow.vue`, add a flash error banner:
```vue
<div v-if="page.props.flash?.error" class="mb-6 rounded-lg bg-red-900/50 border border-red-700 p-3 text-sm text-red-300">
    {{ page.props.flash.error }}
</div>
```

## Important constraints
- Do NOT break existing functionality
- Keep the try-catch only for debugging, we can remove it later
