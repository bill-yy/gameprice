<?php

namespace App\Services\Affiliates;

use App\Models\Game;

class HumbleBundleService extends BaseAffiliateService
{
    public function getStoreSlug(): string
    {
        return 'humble-bundle';
    }

    public function getAffiliateUrl(Game $game): string
    {
        $query = urlencode($game->title);

        return "https://www.humblebundle.com/store/search?aff_id=YOUR_ID&search={$query}";
    }

    protected function getMinDiscount(): int
    {
        return 10;
    }

    protected function getMaxDiscount(): int
    {
        return 50;
    }
}
