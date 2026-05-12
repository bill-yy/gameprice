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
Schedule::command('gamivo:scrape --limit=50')->dailyAt('04:30');
Schedule::command('gamivo:import-json')->dailyAt('05:00');
Schedule::command('prices:import-grey-market-json')->dailyAt('05:00');
Schedule::command('g2a:scrape --limit=50')->dailyAt('05:30');
Schedule::command('g2a:import-json')->dailyAt('06:00');
Schedule::command('instantgaming:scrape --limit=50')->dailyAt('06:30');
Schedule::command('instantgaming:import-json')->dailyAt('07:00');
Schedule::command('kinguin:scrape --limit=50')->dailyAt('07:30');
Schedule::command('kinguin:import-json')->dailyAt('08:00');
// Schedule::command('prices:scrape-cheapshark --pages=10')->everyThreeHours(); // disabled: rate-limited from container
