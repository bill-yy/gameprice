<?php

namespace App\Services\CheapShark;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheapSharkService
{
    private const string BASE_URL = 'https://www.cheapshark.com/api/1.0';

    public function getDeals(array $params = []): array
    {
        try {
            $response = Http::get(self::BASE_URL.'/deals', array_merge([
                'pageSize' => 60,
                'sortBy' => 'Deal Rating',
            ], $params));

            if ($response->failed()) {
                Log::error('CheapShark deals API request failed', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('CheapShark deals API connection error', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getDealById(string $id): array
    {
        try {
            $response = Http::get(self::BASE_URL.'/deals', [
                'id' => $id,
            ]);

            if ($response->failed()) {
                Log::error('CheapShark deal lookup API request failed', [
                    'id' => $id,
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('CheapShark deal lookup API connection error', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getStores(): array
    {
        try {
            $response = Http::get(self::BASE_URL.'/stores');

            if ($response->failed()) {
                Log::error('CheapShark stores API request failed', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('CheapShark stores API connection error', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
