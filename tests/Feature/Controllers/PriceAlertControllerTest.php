<?php

namespace Tests\Feature\Controllers;

use App\Models\Game;
use App\Models\PriceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_alert(): void
    {
        $game = Game::factory()->create();

        $response = $this->post('/alerts', [
            'game_id' => $game->id,
            'email' => 'test@example.com',
            'target_price' => 19.99,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('price_alerts', [
            'game_id' => $game->id,
            'email' => 'test@example.com',
            'target_price' => 19.99,
            'is_active' => true,
        ]);
    }

    public function test_store_requires_valid_game_id(): void
    {
        $response = $this->post('/alerts', [
            'game_id' => 99999,
            'email' => 'test@example.com',
            'target_price' => 19.99,
        ]);

        $response->assertSessionHasErrors('game_id');
    }

    public function test_store_requires_valid_email(): void
    {
        $game = Game::factory()->create();

        $response = $this->post('/alerts', [
            'game_id' => $game->id,
            'email' => 'not-an-email',
            'target_price' => 19.99,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_store_requires_target_price(): void
    {
        $game = Game::factory()->create();

        $response = $this->post('/alerts', [
            'game_id' => $game->id,
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('target_price');
    }

    public function test_store_requires_positive_target_price(): void
    {
        $game = Game::factory()->create();

        $response = $this->post('/alerts', [
            'game_id' => $game->id,
            'email' => 'test@example.com',
            'target_price' => -5,
        ]);

        $response->assertSessionHasErrors('target_price');
    }

    public function test_store_requires_game_id(): void
    {
        $response = $this->post('/alerts', [
            'email' => 'test@example.com',
            'target_price' => 19.99,
        ]);

        $response->assertSessionHasErrors('game_id');
    }
}
