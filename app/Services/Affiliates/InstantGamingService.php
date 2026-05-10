<?php

namespace App\Services\Affiliates;

use App\Models\Game;

class InstantGamingService extends BaseAffiliateService
{
    public function getStoreSlug(): string
    {
        return 'instant-gaming';
    }

    public function getAffiliateUrl(Game $game): string
    {
        $query = urlencode($game->title);

        return "https://www.instant-gaming.com/?aff=YOUR_ID&search={$query}";
    }

    protected function getMinDiscount(): int
    {
        return 25;
    }

    protected function getMaxDiscount(): int
    {
        return 65;
    }
}
