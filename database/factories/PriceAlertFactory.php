<?php

namespace Database\Factories;

use App\Models\PriceAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceAlertFactory extends Factory
{
    protected $model = PriceAlert::class;

    public function definition(): array
    {
        return [
            'game_id' => \App\Models\Game::factory(),
            'email' => fake()->safeEmail(),
            'target_price' => fake()->randomFloat(2, 5, 30),
            'is_active' => true,
        ];
    }
}
