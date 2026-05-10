<?php

namespace Tests\Feature\Commands;

use App\Models\Game;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeAllPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_without_error(): void
    {
        $this->artisan('prices:scrape-all')
            ->assertSuccessful();
    }

    public function test_command_reports_no_active_games(): void
    {
        $this->artisan('prices:scrape-all')
            ->expectsOutput('No active games found.')
            ->assertSuccessful();
    }

    public function test_command_creates_products_for_active_games_and_stores(): void
    {
        $game = Game::factory()->create(['is_active' => true, 'steam_app_id' => 12345]);
        $store = Store::factory()->create(['slug' => 'eneba', 'is_active' => true]);

        $this->artisan('prices:scrape-all')
            ->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'game_id' => $game->id,
            'store_id' => $store->id,
        ]);
    }

    public function test_command_skips_inactive_games(): void
    {
        Game::factory()->create(['is_active' => false, 'steam_app_id' => 99999]);
        Store::factory()->create(['slug' => 'eneba', 'is_active' => true]);

        $this->artisan('prices:scrape-all')
            ->expectsOutput('No active games found.')
            ->assertSuccessful();
    }
}
