<?php

namespace App\Services\Affiliates;

use App\Models\Game;

class FanaticalService extends BaseAffiliateService
{
    public function getStoreSlug(): string
    {
        return 'fanatical';
    }

    public function getAffiliateUrl(Game $game): string
    {
        $query = urlencode($game->title);

        return "https://www.fanatical.com/en/search?aff_id=YOUR_ID&query={$query}";
    }

    protected function getMinDiscount(): int
    {
        return 20;
    }

    protected function getMaxDiscount(): int
    {
        return 80;
    }
}
