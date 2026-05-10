<?php

namespace App\Services\Affiliates;

use App\Models\Game;

class KinguinService extends BaseAffiliateService
{
    public function getStoreSlug(): string
    {
        return 'kinguin';
    }

    public function getAffiliateUrl(Game $game): string
    {
        $query = urlencode($game->title);

        return "https://www.kinguin.net/listing?r=YOUR_ID&search={$query}";
    }

    protected function getMinDiscount(): int
    {
        return 25;
    }

    protected function getMaxDiscount(): int
    {
        return 70;
    }
}
