<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $originalPrice = fake()->randomFloat(2, 9.99, 79.99);
        $discount = fake()->numberBetween(0, 70);

        return [
            'game_id' => \App\Models\Game::factory(),
            'store_id' => \App\Models\Store::factory(),
            'type' => 'key',
            'platform' => 'PC',
            'region' => 'global',
            'edition' => 'Standard',
            'url' => fake()->url(),
            'affiliate_url' => fake()->url(),
            'current_price' => round($originalPrice * (1 - $discount / 100), 2),
            'original_price' => $originalPrice,
            'discount_percent' => $discount,
            'currency' => 'EUR',
            'in_stock' => true,
        ];
    }
}
