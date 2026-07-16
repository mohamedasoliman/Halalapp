<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Schedule::command('db:backup')->daily()->at('03:00');
Schedule::command('analytics:prune --days=90')->daily()->at('04:30');
Schedule::command('brands:follow-up --prepare')
    ->dailyAt('08:00')
    ->timezone('Pacific/Auckland')
    ->withoutOverlapping();

if (config('outreach.enabled')) {
    Schedule::command('queue:work database --queue=outreach --stop-when-empty --max-time=50 --tries=1')
        ->everyMinute()
        ->withoutOverlapping();
}
