<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'website' => fake()->url(),
            'logo_url' => fake()->imageUrl(200, 200, 'logo'),
            'affiliate_program' => fake()->word(),
            'commission_rate' => fake()->randomFloat(2, 1, 15),
            'is_official' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}
