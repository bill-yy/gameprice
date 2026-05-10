<?php

namespace App\Services\Affiliates;

use App\Models\Game;

class EnebaService extends BaseAffiliateService
{
    public function getStoreSlug(): string
    {
        return 'eneba';
    }

    public function getAffiliateUrl(Game $game): string
    {
        $query = urlencode($game->title);

        return "https://www.eneba.com/?aff_id=YOUR_ID&query={$query}";
    }

    protected function getMinDiscount(): int
    {
        return 30;
    }

    protected function getMaxDiscount(): int
    {
        return 75;
    }
}
