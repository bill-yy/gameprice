<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('steam:update-games --limit=50')->everySixHours();
Schedule::command('prices:scrape-all')->everySixHours();
Schedule::command('alerts:check')->hourly();
Schedule::command('prices:import-cheapshark-json')->dailyAt('03:00');
Schedule::command('prices:scrape-eneba --limit=50')->dailyAt('04:00');
Schedule::command('prices:import-grey-market-json')->dailyAt('05:00');
// Schedule::command('prices:scrape-cheapshark --pages=10')->everyThreeHours(); // disabled: rate-limited from container
