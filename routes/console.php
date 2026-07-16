<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Telegram study proaktiv push — settings-driven (deck, interval, aktiv saatlar)
Schedule::command('telegram:study-push')->everyMinute()->withoutOverlapping();
