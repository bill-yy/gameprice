<?php

namespace Tests\Feature\Commands;

use App\Models\Game;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckPriceAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_without_error(): void
    {
        $this->artisan('alerts:check')
            ->assertSuccessful();
    }

    public function test_command_notifies_when_price_below_target(): void
    {
        $game = Game::factory()->create();
        $store = Store::factory()->create();
        Product::factory()->create([
            'game_id' => $game->id,
            'store_id' => $store->id,
            'current_price' => 15.00,
        ]);

        $alert = PriceAlert::factory()->create([
            'game_id' => $game->id,
            'target_price' => 20.00,
            'is_active' => true,
        ]);

        $this->artisan('alerts:check')
            ->assertSuccessful();

        $alert->refresh();
        $this->assertFalse($alert->is_active);
        $this->assertNotNull($alert->notified_at);
    }

    public function test_command_does_not_notify_when_price_above_target(): void
    {
        $game = Game::factory()->create();
        $store = Store::factory()->create();
        Product::factory()->create([
            'game_id' => $game->id,
            'store_id' => $store->id,
            'current_price' => 30.00,
        ]);

        $alert = PriceAlert::factory()->create([
            'game_id' => $game->id,
            'target_price' => 20.00,
            'is_active' => true,
        ]);

        $this->artisan('alerts:check')
            ->assertSuccessful();

        $alert->refresh();
        $this->assertTrue($alert->is_active);
        $this->assertNull($alert->notified_at);
    }

    public function test_command_skips_already_notified_alerts(): void
    {
        $game = Game::factory()->create();
        $store = Store::factory()->create();
        Product::factory()->create([
            'game_id' => $game->id,
            'store_id' => $store->id,
            'current_price' => 10.00,
        ]);

        $alert = PriceAlert::factory()->create([
            'game_id' => $game->id,
            'target_price' => 20.00,
            'is_active' => true,
            'notified_at' => now(),
        ]);

        $this->artisan('alerts:check')
            ->assertSuccessful();

        $alert->refresh();
        $this->assertTrue($alert->is_active);
    }
}
