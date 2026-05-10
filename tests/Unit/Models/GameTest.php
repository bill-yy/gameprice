<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_has_correct_fillable_attributes(): void
    {
        $game = new Game;

        $fillable = $game->getFillable();

        $expected = [
            'slug', 'title', 'description', 'release_date', 'cover_image',
            'steam_app_id', 'platforms', 'genres', 'developer', 'publisher',
            'metacritic_score', 'is_active',
        ];

        $this->assertEquals($expected, $fillable);
    }

    public function test_game_casts_platforms_as_array(): void
    {
        $game = Game::factory()->create(['platforms' => ['windows', 'linux']]);

        $this->assertIsArray($game->platforms);
        $this->assertEquals(['windows', 'linux'], $game->platforms);
    }

    public function test_game_casts_genres_as_array(): void
    {
        $game = Game::factory()->create(['genres' => ['Action', 'RPG']]);

        $this->assertIsArray($game->genres);
        $this->assertEquals(['Action', 'RPG'], $game->genres);
    }

    public function test_game_casts_release_date_as_date(): void
    {
        $game = Game::factory()->create(['release_date' => '2024-06-15']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $game->release_date);
    }

    public function test_game_route_key_is_slug(): void
    {
        $game = new Game;

        $this->assertEquals('slug', $game->getRouteKeyName());
    }

    public function test_game_has_products_relationship(): void
    {
        $game = Game::factory()->create();
        $product = Product::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($game->products->contains($product));
        $this->assertEquals(1, $game->products->count());
    }

    public function test_game_has_reviews_relationship(): void
    {
        $game = Game::factory()->create();
        Review::create([
            'game_id' => $game->id,
            'user_name' => 'Tester',
            'user_email' => 'test@example.com',
            'rating' => 4,
            'comment' => 'Great game',
            'is_approved' => true,
        ]);

        $this->assertEquals(1, $game->reviews->count());
    }
}
