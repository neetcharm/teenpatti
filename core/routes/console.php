<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule the live game scheduler to run every minute
Schedule::command('game:scheduler-run')->everyMinute()->withoutOverlapping(30);

// Teen Patti 24/7 round resolver — ensures history is persisted even with no players
Schedule::command('teen-patti:resolve')->everyMinute()->withoutOverlapping(15);

// Andar Bahar 24/7 round resolver
Schedule::command('andar-bahar:resolve')->everyMinute()->withoutOverlapping(15);
