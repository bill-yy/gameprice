<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            [
                'store_slug' => 'fanatical',
                'code' => 'SAVE5',
                'discount_value' => 5.00,
                'discount_type' => 'percentage',
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-12-31',
            ],
            [
                'store_slug' => 'gamersgate',
                'code' => 'GG10',
                'discount_value' => 10.00,
                'discount_type' => 'percentage',
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-12-31',
            ],
            [
                'store_slug' => 'gamesplanet',
                'code' => 'PLANET15',
                'discount_value' => 15.00,
                'discount_type' => 'percentage',
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-12-31',
            ],
        ];

        foreach ($vouchers as $voucherData) {
            $store = Store::where('slug', $voucherData['store_slug'])->first();

            if (! $store) {
                $store = Store::updateOrCreate(
                    ['slug' => $voucherData['store_slug']],
                    [
                        'name' => 'GamersGate',
                        'website' => 'https://www.gamersgate.com',
                        'is_official' => true,
                        'is_active' => true,
                    ]
                );
            }

            Voucher::updateOrCreate(
                ['store_id' => $store->id, 'code' => $voucherData['code']],
                [
                    'discount_value' => $voucherData['discount_value'],
                    'discount_type' => $voucherData['discount_type'],
                    'valid_from' => $voucherData['valid_from'],
                    'valid_until' => $voucherData['valid_until'],
                    'is_active' => true,
                ]
            );
        }
    }
}
