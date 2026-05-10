<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearPriceCache extends Command
{
    protected $signature = 'cache:clear-prices';

    protected $description = 'Clear all application cache';

    public function handle(): int
    {
        Cache::flush();
        $this->info('Cache cleared successfully.');

        return self::SUCCESS;
    }
}
