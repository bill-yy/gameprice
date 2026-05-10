<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('steam:update-games --limit=50')->everySixHours();
Schedule::command('prices:update')->everySixHours();
Schedule::command('alerts:check')->hourly();
Schedule::command('prices:scrape-all')->everyThreeHours();
