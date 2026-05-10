<?php

namespace Tests\Unit\Services;

use App\Models\Game;
use App\Services\Affiliates\EnebaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseAffiliateServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnebaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EnebaService;
    }

    public function test_same_seed_yields_same_price(): void
    {
        $game = Game::factory()->create([
            'steam_app_id' => 123456,
            'title' => 'Test Game',
        ]);

        $result1 = $this->service->getPriceForGame($game);
        $result2 = $this->service->getPriceForGame($game);

        $this->assertEquals($result1['current_price'], $result2['current_price']);
        $this->assertEquals($result1['original_price'], $result2['original_price']);
        $this->assertEquals($result1['discount_percentage'], $result2['discount_percentage']);
    }

    public function test_returns_nulls_when_no_steam_app_id(): void
    {
        $game = Game::factory()->create([
            'steam_app_id' => null,
            'title' => 'No Steam Game',
        ]);

        $result = $this->service->getPriceForGame($game);

        $this->assertNull($result['current_price']);
        $this->assertNull($result['original_price']);
        $this->assertEquals(0, $result['discount_percentage']);
        $this->assertFalse($result['is_available']);
    }

    public function test_discount_is_within_expected_range(): void
    {
        $game = Game::factory()->create([
            'steam_app_id' => 789012,
            'title' => 'Range Test Game',
        ]);

        $results = [];
        for ($i = 1; $i <= 100; $i++) {
            $game->steam_app_id = $i;
            $game->save();
            $result = $this->service->getPriceForGame($game);
            if ($result['is_available']) {
                $results[] = $result['discount_percentage'];
            }
        }

        foreach ($results as $discount) {
            $this->assertGreaterThanOrEqual(30, $discount);
            $this->assertLessThanOrEqual(75, $discount);
        }
    }

    public function test_current_price_never_exceeds_original_price(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $game = Game::factory()->create([
                'steam_app_id' => $i * 7,
                'title' => "Price Test {$i}",
            ]);

            $result = $this->service->getPriceForGame($game);

            if ($result['is_available']) {
                $this->assertLessThanOrEqual(
                    $result['original_price'],
                    $result['current_price'],
                    "current_price exceeds original_price for steam_app_id={$game->steam_app_id}"
                );
            }
        }
    }

    public function test_original_price_is_from_tiers(): void
    {
        $priceTiers = [
            9.99, 14.99, 19.99, 24.99, 29.99,
            34.99, 39.99, 44.99, 49.99, 54.99,
            59.99, 69.99, 79.99,
        ];

        for ($i = 1; $i <= 30; $i++) {
            $game = Game::factory()->create([
                'steam_app_id' => $i * 13,
                'title' => "Tier Test {$i}",
            ]);

            $result = $this->service->getPriceForGame($game);

            if ($result['is_available']) {
                $this->assertContains(
                    $result['original_price'],
                    $priceTiers,
                    "Original price not in expected tiers for steam_app_id={$game->steam_app_id}"
                );
            }
        }
    }

    public function test_returns_url_for_game(): void
    {
        $game = Game::factory()->create([
            'steam_app_id' => 123,
            'title' => 'URL Test Game',
        ]);

        $result = $this->service->getPriceForGame($game);

        $this->assertStringContainsString('eneba.com', $result['url']);
        $this->assertStringContainsString(urlencode($game->title), $result['url']);
    }
}
