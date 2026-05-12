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
