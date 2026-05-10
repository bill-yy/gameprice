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
                'logo_url' => null,
                'affiliate_program' => 'instant-gaming',
                'commission_rate' => 5.00,
                'is_official' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Kinguin',
                'slug' => 'kinguin',
                'website' => 'https://www.kinguin.net',
                'logo_url' => null,
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
        ];

        foreach ($stores as $store) {
            Store::create($store);
        }
    }
}
