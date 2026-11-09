<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Feature 29: saved-search job alerts
Schedule::command('saved-searches:dispatch-alerts daily')->daily()->at('07:05');
Schedule::command('saved-searches:dispatch-alerts weekly')->weeklyOn(1, '07:15');
