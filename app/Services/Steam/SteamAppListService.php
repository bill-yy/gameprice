<?php

namespace App\Services\Steam;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteamAppListService
{
    private const string API_URL = 'https://api.steampowered.com/ISteamApps/GetAppList/v2/';

    public function fetchAllApps(): array
    {
        try {
            $response = Http::get(self::API_URL);

            if ($response->failed()) {
                Log::error('Steam App List API request failed', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();

            return $data['applist']['apps'] ?? [];
        } catch (ConnectionException $e) {
            Log::error('Steam App List API connection error', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
