<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    public function run(): void
    {
        // Master/admin key (unlimited)
        ApiKey::firstOrCreate(
            ['key' => '053c47f03aba907891b2d52f22c5965e667fea4f7b3d39e254b4a66b26489bd3'],
            [
                'name' => 'GamePrice Master Key',
                'plan' => 'ultra',
                'requests_limit_daily' => 0,
                'rate_limit_per_minute' => 300,
                'is_active' => true,
            ]
        );

        // Demo keys for each plan (for testing)
        $plans = ['free', 'basic', 'pro', 'ultra'];
        foreach ($plans as $plan) {
            ApiKey::firstOrCreate(
                ['key' => hash('sha256', "demo-{$plan}-key")],
                [
                    'name' => "Demo {$plan} key",
                    'plan' => $plan,
                    'requests_limit_daily' => ApiKey::planConfig($plan)['requests_limit_daily'],
                    'rate_limit_per_minute' => ApiKey::planConfig($plan)['rate_limit_per_minute'],
                    'is_active' => true,
                ]
            );
        }
    }
}
