<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('articles:fetch-hourly')->hourly();
Schedule::command('standings:fetch')->everyThirtyMinutes();
Schedule::command('world-cup:sync')->everyFifteenMinutes();
Schedule::command('premier-liga:sync')->everyTwoMinutes();
Schedule::command('fixtures:sync --live')->everyMinute();
Schedule::command('fixtures:sync --tracked')->everyFiveMinutes();
Schedule::command('transfers:sync')->dailyAt('06:00');
Schedule::command('clubs:sync-daily --batch=15')
    ->dailyAt('04:00')
    ->withoutOverlapping(360)
    ->appendOutputTo(storage_path('logs/clubs-sync-daily.log'));
