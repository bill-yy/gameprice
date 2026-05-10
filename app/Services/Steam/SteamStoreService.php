<?php

namespace App\Services\Steam;

use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteamStoreService
{
    private const string STORE_API_URL = 'https://store.steampowered.com/api/appdetails';

    public function fetchGameDetails(string $appId): ?array
    {
        try {
            $response = Http::get(self::STORE_API_URL, [
                'appids' => $appId,
                'cc' => 'ES',
                'l' => 'spanish',
            ]);

            if ($response->failed()) {
                Log::error('Steam Store API request failed', [
                    'app_id' => $appId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json();
            $appData = $body[$appId] ?? null;

            if (! $appData || ! ($appData['success'] ?? false)) {
                return null;
            }

            return $appData['data'] ?? null;
        } catch (ConnectionException $e) {
            Log::error('Steam Store API connection error', [
                'app_id' => $appId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateGameFromSteam(Game $game): bool
    {
        if (! $game->steam_app_id) {
            return false;
        }

        $data = $this->fetchGameDetails((string) $game->steam_app_id);

        if (! $data) {
            return false;
        }

        $game->fill([
            'title' => $data['name'] ?? $game->title,
            'description' => $data['short_description'] ?? $data['detailed_description'] ?? $game->description,
            'release_date' => $this->parseReleaseDate($data['release_date']['date'] ?? null),
            'cover_image' => $data['header_image'] ?? $game->cover_image,
            'platforms' => $this->parsePlatforms($data['platforms'] ?? []),
            'genres' => $this->parseGenres($data['genres'] ?? []),
            'developer' => $data['developers'][0] ?? $game->developer,
            'publisher' => $data['publishers'][0] ?? $game->publisher,
            'metacritic_score' => $data['metacritic']['score'] ?? $game->metacritic_score,
        ])->save();

        usleep(200000);

        return true;
    }

    private function parseReleaseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function parsePlatforms(array $platforms): array
    {
        return array_keys(array_filter($platforms));
    }

    private function parseGenres(array $genres): array
    {
        return array_map(fn (array $genre) => $genre['description'] ?? '', $genres);
    }
}
