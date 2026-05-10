<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAllPrices extends Command
{
    protected $signature = 'prices:update';

    protected $description = 'Update all product prices from APIs';

    public function handle(): int
    {
        Log::info('Price update not yet implemented');
        $this->info('Price update not yet implemented.');

        return self::SUCCESS;
    }
}
