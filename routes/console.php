<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps subscription status accurate and notifies clients of
// expiring/expired subscriptions and devices at their package limit.
// Requires the scheduler to actually be running (`schedule:work` locally,
// a cron entry calling `schedule:run` every minute in production).
Schedule::command('platform:check-subscriptions-and-devices')->daily();
