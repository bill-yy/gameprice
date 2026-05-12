<?php

namespace App\Services;

use App\Models\Game;
use App\Jobs\FetchPricesForGame;
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
        $steamApp = $this->searchSteam($query);
        if (!$steamApp) {
            return null;
        }

        $existing = Game::where('steam_app_id', $steamApp['appid'])->first();
        if ($existing) {
            return $existing;
        }

        $game = Game::create([
            'title' => $steamApp['name'],
            'slug' => \Illuminate\Support\Str::slug($steamApp['name']),
            'steam_app_id' => $steamApp['appid'],
            'is_active' => true,
        ]);

        dispatch(new \App\Jobs\FetchSteamGameDetails($game));
        dispatch(new FetchPricesForGame($game));

        return $game;
    }

    private function searchSteam(string $query): ?array
    {
        try {
            // Use Steam Store Search API (fast, reliable, supports partial matches)
            $response = Http::timeout(15)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->get('https://store.steampowered.com/api/storesearch/', [
                'term' => $query,
                'l' => 'english',
                'cc' => 'ES',
            ]);

            $items = $response->json('items') ?? [];
            $queryLower = strtolower($query);

            // Exact match first
            foreach ($items as $item) {
                if (strtolower($item['name'] ?? '') === $queryLower) {
                    return ['appid' => $item['id'], 'name' => $item['name']];
                }
            }

            // Partial match
            foreach ($items as $item) {
                $name = strtolower($item['name'] ?? '');
                if (str_contains($name, $queryLower)) {
                    return ['appid' => $item['id'], 'name' => $item['name']];
                }
            }

            // Fallback: first result if query words are present
            foreach ($items as $item) {
                $name = strtolower($item['name'] ?? '');
                $words = explode(' ', $queryLower);
                $matches = 0;
                foreach ($words as $word) {
                    if (strlen($word) > 2 && str_contains($name, $word)) {
                        $matches++;
                    }
                }
                if ($matches >= count($words) / 2) {
                    return ['appid' => $item['id'], 'name' => $item['name']];
                }
            }
        } catch (\Exception $e) {
            Log::error('Steam storesearch failed: ' . $e->getMessage());
        }
        return null;
    }
}
