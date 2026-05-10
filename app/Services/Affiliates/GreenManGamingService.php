<?php

namespace App\Services\Affiliates;

use App\Models\Game;

class GreenManGamingService extends BaseAffiliateService
{
    public function getStoreSlug(): string
    {
        return 'green-man-gaming';
    }

    public function getAffiliateUrl(Game $game): string
    {
        $query = urlencode($game->title);

        return "https://www.greenmangaming.com/search?aff_id=YOUR_ID&query={$query}";
    }

    protected function getMinDiscount(): int
    {
        return 15;
    }

    protected function getMaxDiscount(): int
    {
        return 70;
    }
}
