<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Use the Schedule facade for all commands
Schedule::command('lms:send-reminders')->hourly();
Schedule::command('lms:generate-recommendations')->daily();