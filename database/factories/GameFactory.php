<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'description' => fake()->paragraph(),
            'release_date' => fake()->date(),
            'cover_image' => fake()->imageUrl(640, 480, 'games'),
            'steam_app_id' => fake()->unique()->randomNumber(6),
            'platforms' => ['windows'],
            'genres' => ['Action', 'Adventure'],
            'developer' => fake()->company(),
            'publisher' => fake()->company(),
            'metacritic_score' => fake()->numberBetween(50, 98),
            'is_active' => true,
        ];
    }
}
