<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = [
            [
                'id' => 'eneba',
                'name' => EnebaScraper::getStoreName(),
                'url' => 'https://www.eneba.com',
                'currency' => 'EUR',
            ],
            [
                'id' => 'instant-gaming',
                'name' => InstantGamingScraper::getStoreName(),
                'url' => 'https://www.instant-gaming.com',
                'currency' => 'EUR',
            ],
            [
                'id' => 'cheapshark',
                'name' => CheapSharkScraper::getStoreName(),
                'url' => 'https://www.cheapshark.com',
                'currency' => 'USD',
            ],
            [
                'id' => 'g2a',
                'name' => G2AScraper::getStoreName(),
                'url' => 'https://www.g2a.com',
                'currency' => 'EUR',
            ],
            [
                'id' => 'kinguin',
                'name' => KinguinScraper::getStoreName(),
                'url' => 'https://www.kinguin.net',
                'currency' => 'EUR',
            ],
        ];

        return response()->json([
            'success' => true,
            'stores' => $stores,
            'meta' => [
                'count' => count($stores),
            ],
        ]);
    }
}
