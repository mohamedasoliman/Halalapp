<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Schedule::command('db:backup')->daily()->at('03:00');
Schedule::command('analytics:prune --days=90')->daily()->at('04:30');
