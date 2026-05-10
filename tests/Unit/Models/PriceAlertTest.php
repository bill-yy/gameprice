<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use App\Models\PriceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_alert_has_correct_fillable_attributes(): void
    {
        $alert = new PriceAlert;

        $fillable = $alert->getFillable();

        $expected = ['game_id', 'email', 'target_price', 'is_active', 'notified_at'];

        $this->assertEquals($expected, $fillable);
    }

    public function test_price_alert_casts_is_active_as_boolean(): void
    {
        $alert = PriceAlert::factory()->create(['is_active' => 1]);

        $this->assertIsBool($alert->is_active);
        $this->assertTrue($alert->is_active);
    }

    public function test_price_alert_casts_target_price_as_decimal(): void
    {
        $alert = PriceAlert::factory()->create(['target_price' => 15.50]);

        $this->assertEquals('15.50', (string) $alert->target_price);
    }

    public function test_price_alert_casts_notified_at_as_datetime(): void
    {
        $alert = PriceAlert::factory()->create(['notified_at' => now()]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $alert->notified_at);
    }

    public function test_price_alert_belongs_to_game(): void
    {
        $alert = PriceAlert::factory()->create();

        $this->assertInstanceOf(Game::class, $alert->game);
    }
}
