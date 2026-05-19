<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Main price scraping - runs every 2 hours to keep cache warm
Schedule::command('gameprice:scrape --stores=all')->everyTwoHours();

// Steam game catalog update
Schedule::command('steam:update-games --limit=50')->everySixHours();

// Price alerts check
Schedule::command('alerts:check')->hourly();

// API rate limit reset
Schedule::call(fn () => \App\Models\ApiKey::resetDailyCounters())->dailyAt('00:00');
