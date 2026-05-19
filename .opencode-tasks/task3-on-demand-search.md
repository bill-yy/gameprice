# Task: On-Demand Game Search

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- GameController::index() handles search via ?search= parameter
- SteamStoreService exists at app/Services/Steam/SteamStoreService.php
- Game model can be created with Steam data
- SearchBar component exists

## What to build

### 1. Create app/Services/OnDemandSearchService.php
```php
<?php

namespace App\Services;

use App\Models\Game;
use App\Services\Steam\SteamStoreService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OnDemandSearchService
{
    public function __construct(
        private SteamStoreService $steamService
    ) {}

    public function search(string $query): ?Game
    {
        // 1. Search Steam API for the game
        $steamApp = $this->searchSteam($query);
        if (!$steamApp) {
            return null;
        }

        // 2. Check if game already exists by steam_app_id
        $existing = Game::where('steam_app_id', $steamApp['appid'])->first();
        if ($existing) {
            return $existing;
        }

        // 3. Create game with basic info
        $game = Game::create([
            'title' => $steamApp['name'],
            'slug' => \Illuminate\Support\Str::slug($steamApp['name']),
            'steam_app_id' => $steamApp['appid'],
            'is_active' => true,
        ]);

        // 4. Fetch full details from Steam in background
        dispatch(new \App\Jobs\FetchSteamGameDetails($game));

        return $game;
    }

    private function searchSteam(string $query): ?array
    {
        try {
            $response = Http::timeout(10)->get(
                'https://api.steampowered.com/ISteamApps/GetAppList/v2/'
            );
            $apps = $response->json('applist.apps') ?? [];
            
            $queryLower = strtolower($query);
            foreach ($apps as $app) {
                if (strtolower($app['name']) === $queryLower) {
                    return $app;
                }
            }
            // Fuzzy match
            foreach ($apps as $app) {
                if (str_contains(strtolower($app['name']), $queryLower)) {
                    return $app;
                }
            }
        } catch (\Exception $e) {
            Log::error('Steam search failed: ' . $e->getMessage());
        }
        return null;
    }
}
```

### 2. Create app/Jobs/FetchSteamGameDetails.php
```php
<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\Steam\SteamStoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchSteamGameDetails implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Game $game) {}

    public function handle(SteamStoreService $steamService): void
    {
        $steamService->updateGameFromSteam($this->game);
    }
}
```

### 3. Update app/Http/Controllers/GameController.php
In the index() method, AFTER the existing cache logic but BEFORE returning, add:

```php
// On-demand search: if no games found and search query provided
if ($search && count($games['data'] ?? []) === 0) {
    $onDemand = app(\App\Services\OnDemandSearchService::class);
    $found = $onDemand->search($search);
    
    if ($found) {
        // Reload with the new game included
        return redirect()->route('game.show', $found->slug);
    }
}
```

### 4. Update resources/js/Components/SearchBar.vue
Add a loading state message when search returns no results and is processing:
- Show "Buscando en tiendas..." after submitting if results are empty
- Use a simple timeout or check for the redirect

### 5. Update app/Providers/AppServiceProvider.php
Register OnDemandSearchService in the container:
```php
$this->app->singleton(\App\Services\OnDemandSearchService::class);
```

## Important
- Do NOT break existing search
- Keep all error handling
- Log all failures
- Only modify the files mentioned above
