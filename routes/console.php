<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Update Steam games catalog
Schedule::command('steam:update-games --limit=50')->everySixHours();

// Update prices from real sources (Eneba, CheapShark, ITAD)
Schedule::command('prices:scrape-real --limit=100')->dailyAt('03:00');

// Check price alerts
Schedule::command('alerts:check')->hourly();

// Legacy placeholder scraper (fake prices) — can be removed once real prices are stable
Schedule::command('prices:scrape-all')->weekly();
