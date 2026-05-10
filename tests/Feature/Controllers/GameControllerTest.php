<?php

namespace Tests\Feature\Controllers;

use App\Models\Game;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_200(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_index_returns_games_with_products(): void
    {
        Game::factory()->count(3)->create(['is_active' => true]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_index_search_filters_by_title(): void
    {
        Game::factory()->create(['title' => 'Cyberpunk 2077', 'is_active' => true]);
        Game::factory()->create(['title' => 'The Witcher 3', 'is_active' => true]);

        $response = $this->get('/?search=Cyberpunk');

        $response->assertStatus(200);
    }

    public function test_show_returns_200(): void
    {
        $game = Game::factory()->create(['is_active' => true]);

        $response = $this->get("/juego/{$game->slug}");

        $response->assertStatus(200);
    }

    public function test_show_returns_404_for_nonexistent_game(): void
    {
        $response = $this->get('/juego/nonexistent-game');

        $response->assertStatus(404);
    }

    public function test_show_loads_products_from_active_stores_only(): void
    {
        $game = Game::factory()->create(['is_active' => true]);
        $activeStore = Store::factory()->create(['is_active' => true, 'slug' => 'active-store']);
        $inactiveStore = Store::factory()->create(['is_active' => false, 'slug' => 'inactive-store']);

        Product::factory()->create([
            'game_id' => $game->id,
            'store_id' => $activeStore->id,
            'current_price' => 19.99,
        ]);
        Product::factory()->create([
            'game_id' => $game->id,
            'store_id' => $inactiveStore->id,
            'current_price' => 9.99,
        ]);

        $response = $this->get("/juego/{$game->slug}");

        $response->assertStatus(200);
    }

    public function test_show_loads_approved_reviews_only(): void
    {
        $game = Game::factory()->create(['is_active' => true]);

        Review::create([
            'game_id' => $game->id,
            'user_name' => 'Approved',
            'user_email' => 'approved@test.com',
            'rating' => 5,
            'comment' => 'Approved review',
            'is_approved' => true,
        ]);
        Review::create([
            'game_id' => $game->id,
            'user_name' => 'Rejected',
            'user_email' => 'rejected@test.com',
            'rating' => 1,
            'comment' => 'Rejected review',
            'is_approved' => false,
        ]);

        $response = $this->get("/juego/{$game->slug}");

        $response->assertStatus(200);
    }

    public function test_index_paginates_results(): void
    {
        Game::factory()->count(30)->create(['is_active' => true]);

        $response = $this->get('/?page=2');

        $response->assertStatus(200);
    }
}
