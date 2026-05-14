<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class LandingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'name' => 'GamePrice API',
            'version' => '1.0.0',
            'description' => 'Real-time game price comparison across 8 digital stores.',
            'base_url' => 'https://baratoya.billytech.es/api/v1',
            'docs_url' => 'https://baratoya.billytech.es/docs',
            'features' => [
                'Search 8 stores simultaneously',
                'Grey market prices (Eneba, G2A, Kinguin, Instant Gaming)',
                'Current deals under $15',
                'Price drop webhooks (Pro/Ultra)',
                'Rate-limited per plan',
            ],
            'endpoints' => [
                'GET /api/health' => 'Health check (no auth)',
                'GET /api/v1/stores' => 'List all stores',
                'GET /api/v1/search?q={game}' => 'Search all stores',
                'GET /api/v1/prices/{store}?q={game}' => 'Search specific store',
                'GET /api/v1/deals' => 'Current best deals',
                'GET /api/v1/webhooks' => 'List webhooks (Pro/Ultra)',
                'POST /api/v1/webhooks' => 'Create webhook (Pro/Ultra)',
                'DELETE /api/v1/webhooks/{id}' => 'Delete webhook',
            ],
            'plans' => [
                'free' => ['price' => '$0', 'requests' => 100],
                'basic' => ['price' => '$9.99/mo', 'requests' => 10000],
                'pro' => ['price' => '$29.99/mo', 'requests' => 100000],
                'ultra' => ['price' => '$99.99/mo', 'requests' => 'unlimited'],
            ],
            'authentication' => 'X-API-Key header required for all endpoints except /health',
            'support' => 'support@gameprice.com',
        ]);
    }
}
