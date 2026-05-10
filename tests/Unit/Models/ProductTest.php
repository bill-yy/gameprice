<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use App\Models\PriceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_correct_fillable_attributes(): void
    {
        $product = new Product;

        $fillable = $product->getFillable();

        $expected = [
            'game_id', 'store_id', 'type', 'platform', 'region', 'edition',
            'url', 'affiliate_url', 'current_price', 'original_price',
            'discount_percent', 'currency', 'in_stock',
        ];

        $this->assertEquals($expected, $fillable);
    }

    public function test_product_belongs_to_game(): void
    {
        $product = Product::factory()->create();

        $this->assertInstanceOf(Game::class, $product->game);
    }

    public function test_product_belongs_to_store(): void
    {
        $product = Product::factory()->create();

        $this->assertInstanceOf(Store::class, $product->store);
    }

    public function test_product_has_price_history_relationship(): void
    {
        $product = Product::factory()->create();
        PriceHistory::create([
            'product_id' => $product->id,
            'price' => 19.99,
            'currency' => 'EUR',
            'recorded_at' => now(),
        ]);

        $this->assertEquals(1, $product->priceHistory->count());
        $this->assertEquals(19.99, $product->priceHistory->first()->price);
    }
}
