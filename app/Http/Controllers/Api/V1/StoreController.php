<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Scrapers\CDKeysScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\InstantGamingScraper;
use App\Services\Scrapers\KinguinScraper;
use App\Services\Scrapers\PSNStoreScraper;
use App\Services\Scrapers\XboxStoreScraper;
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
            [
                'id' => 'cdkeys',
                'name' => CDKeysScraper::getStoreName(),
                'url' => 'https://www.cdkeys.com',
                'currency' => 'EUR',
            ],
            [
                'id' => 'psn-store',
                'name' => PSNStoreScraper::getStoreName(),
                'url' => 'https://store.playstation.com',
                'currency' => 'EUR',
            ],
            [
                'id' => 'xbox-store',
                'name' => XboxStoreScraper::getStoreName(),
                'url' => 'https://www.xbox.com',
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
