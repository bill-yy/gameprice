<?php

namespace App\Services\Affiliates;

use App\Models\Game;

abstract class BaseAffiliateService
{
    abstract public function getStoreSlug(): string;

    abstract public function getAffiliateUrl(Game $game): string;

    abstract protected function getMinDiscount(): int;

    abstract protected function getMaxDiscount(): int;

    public function getPriceForGame(Game $game): array
    {
        if (! $game->steam_app_id) {
            return [
                'current_price' => null,
                'original_price' => null,
                'discount_percentage' => 0,
                'url' => $this->getAffiliateUrl($game),
                'is_available' => false,
            ];
        }

        $seed = crc32((string) $game->steam_app_id . $this->getStoreSlug());
        mt_srand($seed);

        $originalPrice = $this->generateOriginalPrice($seed);
        $discount = mt_rand($this->getMinDiscount(), $this->getMaxDiscount());
        $currentPrice = round($originalPrice * (1 - $discount / 100), 2);

        // Ensure current price is never higher than original
        if ($currentPrice > $originalPrice) {
            $currentPrice = $originalPrice;
            $discount = 0;
        }

        $isAvailable = mt_rand(0, 100) > 5; // 95% availability

        mt_srand();

        return [
            'current_price' => $currentPrice,
            'original_price' => $originalPrice,
            'discount_percentage' => $discount,
            'url' => $this->getAffiliateUrl($game),
            'is_available' => $isAvailable,
        ];
    }

    private function generateOriginalPrice(int $seed): float
    {
        mt_srand($seed + 1);

        $priceTiers = [
            9.99, 14.99, 19.99, 24.99, 29.99,
            34.99, 39.99, 44.99, 49.99, 54.99,
            59.99, 69.99, 79.99,
        ];

        $index = mt_rand(0, count($priceTiers) - 1);

        return $priceTiers[$index];
    }
}
