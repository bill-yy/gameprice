<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Scrapers\AllKeyShopScraper;
use App\Services\Scrapers\CheapSharkScraper;
use App\Services\Scrapers\EnebaScraper;
use App\Services\Scrapers\GamesplanetScraper;
use App\Services\Scrapers\G2AScraper;
use App\Services\Scrapers\GamivoScraper;
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
                'status' => 'active',
            ],
            [
                'id' => 'instant-gaming',
                'name' => InstantGamingScraper::getStoreName(),
                'url' => 'https://www.instant-gaming.com',
                'currency' => 'EUR',
                'status' => 'active',
            ],
            [
                'id' => 'cheapshark',
                'name' => CheapSharkScraper::getStoreName(),
                'url' => 'https://www.cheapshark.com',
                'currency' => 'USD',
                'status' => 'active',
            ],
            [
                'id' => 'gamesplanet',
                'name' => GamesplanetScraper::getStoreName(),
                'url' => 'https://www.gamesplanet.com',
                'currency' => 'EUR',
                'status' => 'active',
            ],
            [
                'id' => 'allkeyshop',
                'name' => AllKeyShopScraper::getStoreName(),
                'url' => 'https://www.allkeyshop.com',
                'currency' => 'EUR',
                'status' => 'active',
                'note' => 'Aggregates prices from Kinguin, G2A, Gamivo, CDKeys, K4G, and more',
            ],
            [
                'id' => 'psn-store',
                'name' => PSNStoreScraper::getStoreName(),
                'url' => 'https://store.playstation.com',
                'currency' => 'EUR',
                'status' => 'active',
            ],
            [
                'id' => 'xbox-store',
                'name' => XboxStoreScraper::getStoreName(),
                'url' => 'https://www.xbox.com',
                'currency' => 'EUR',
                'status' => 'active',
            ],
            // Indirectly covered by AllKeyShop
            [
                'id' => 'kinguin',
                'name' => KinguinScraper::getStoreName(),
                'url' => 'https://www.kinguin.net',
                'currency' => 'EUR',
                'status' => 'inactive',
                'note' => 'Blocked by Cloudflare. Prices available via AllKeyShop',
            ],
            [
                'id' => 'g2a',
                'name' => G2AScraper::getStoreName(),
                'url' => 'https://www.g2a.com',
                'currency' => 'EUR',
                'status' => 'inactive',
                'note' => 'Blocked by Cloudflare. Prices available via AllKeyShop',
            ],
            [
                'id' => 'gamivo',
                'name' => GamivoScraper::getStoreName(),
                'url' => 'https://www.gamivo.com',
                'currency' => 'EUR',
                'status' => 'inactive',
                'note' => 'Blocked by Cloudflare. Prices available via AllKeyShop',
            ],
        ];

        return response()->json([
            'success' => true,
            'stores' => $stores,
            'meta' => [
                'count' => count($stores),
                'active' => count(array_filter($stores, fn ($s) => $s['status'] === 'active')),
                'inactive' => count(array_filter($stores, fn ($s) => $s['status'] === 'inactive')),
            ],
        ]);
    }
}
