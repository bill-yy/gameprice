<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            [
                'name' => 'Eneba',
                'slug' => 'eneba',
                'website' => 'https://www.eneba.com',
                'logo_url' => null,
                'affiliate_program' => 'eneba',
                'commission_rate' => 5.00,
                'is_official' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Instant Gaming',
                'slug' => 'instant-gaming',
                'website' => 'https://www.instant-gaming.com',
                'logo_url' => 'https://www.instant-gaming.com/favicon.ico',
                'affiliate_program' => 'instant-gaming',
                'commission_rate' => 5.00,
                'is_official' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Kinguin',
                'slug' => 'kinguin',
                'website' => 'https://www.kinguin.net',
                'logo_url' => 'https://www.kinguin.net/favicon.ico',
                'affiliate_program' => 'kinguin',
                'commission_rate' => 5.00,
                'is_official' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Fanatical',
                'slug' => 'fanatical',
                'website' => 'https://www.fanatical.com',
                'logo_url' => null,
                'affiliate_program' => 'awin',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Green Man Gaming',
                'slug' => 'green-man-gaming',
                'website' => 'https://www.greenmangaming.com',
                'logo_url' => null,
                'affiliate_program' => 'impact',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Humble Bundle',
                'slug' => 'humble-bundle',
                'website' => 'https://www.humblebundle.com',
                'logo_url' => null,
                'affiliate_program' => 'impact',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'GOG',
                'slug' => 'gog',
                'website' => 'https://www.gog.com',
                'logo_url' => null,
                'affiliate_program' => 'gog',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Epic Games Store',
                'slug' => 'epic-games-store',
                'website' => 'https://store.epicgames.com',
                'logo_url' => null,
                'affiliate_program' => 'epic',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Gamesplanet',
                'slug' => 'gamesplanet',
                'website' => 'https://www.gamesplanet.com',
                'logo_url' => null,
                'affiliate_program' => 'gamesplanet',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => '2Game',
                'slug' => '2game',
                'website' => 'https://www.2game.com',
                'logo_url' => null,
                'affiliate_program' => '2game',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'WinGameStore',
                'slug' => 'wingamestore',
                'website' => 'https://www.wingamestore.com',
                'logo_url' => null,
                'affiliate_program' => 'wingamestore',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'GameBillet',
                'slug' => 'gamebillet',
                'website' => 'https://www.gamebillet.com',
                'logo_url' => null,
                'affiliate_program' => 'gamebillet',
                'commission_rate' => 5.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Steam',
                'slug' => 'steam',
                'website' => 'https://store.steampowered.com',
                'logo_url' => null,
                'affiliate_program' => 'steam',
                'commission_rate' => 0.00,
                'is_official' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Gamivo',
                'slug' => 'gamivo',
                'website' => 'https://www.gamivo.com',
                'logo_url' => 'https://www.gamivo.com/favicon.ico',
                'affiliate_program' => null,
                'commission_rate' => 0.00,
                'is_official' => false,
                'is_active' => true,
            ],
            [
                'name' => 'G2A',
                'slug' => 'g2a',
                'website' => 'https://www.g2a.com',
                'logo_url' => 'https://www.g2a.com/favicon.ico',
                'affiliate_program' => null,
                'commission_rate' => 0.00,
                'is_official' => false,
                'is_active' => true,
            ],
        ];

        foreach ($stores as $store) {
            Store::updateOrCreate(
                ['slug' => $store['slug']],
                $store
            );
        }

        $ratings = [
            'steam' => ['rating' => 4.5, 'review_count' => 1000000],
            'gog' => ['rating' => 4.7, 'review_count' => 500000],
            'epic-games-store' => ['rating' => 3.8, 'review_count' => 800000],
            'humble-bundle' => ['rating' => 4.6, 'review_count' => 400000],
            'fanatical' => ['rating' => 4.4, 'review_count' => 300000],
            'gamesplanet' => ['rating' => 4.5, 'review_count' => 200000],
            'green-man-gaming' => ['rating' => 4.3, 'review_count' => 250000],
            'eneba' => ['rating' => 4.0, 'review_count' => 100000],
            'g2a' => ['rating' => 3.5, 'review_count' => 200000],
            'gamivo' => ['rating' => 3.8, 'review_count' => 50000],
            'kinguin' => ['rating' => 3.6, 'review_count' => 80000],
            'instant-gaming' => ['rating' => 4.2, 'review_count' => 120000],
        ];

        foreach ($ratings as $slug => $data) {
            Store::where('slug', $slug)->update($data);
        }

        Store::whereNotIn('slug', array_keys($ratings))->update([
            'rating' => 4.0,
            'review_count' => 10000,
        ]);
    }
}
