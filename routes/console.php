<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$cleanupIntervalMinutes = config('access.authentication.expiredChallengeCleanupIntervalMinutes');

if ($cleanupIntervalMinutes < 1 || $cleanupIntervalMinutes > 59) {
    throw new LogicException('ACCESS_EXPIRED_CHALLENGE_CLEANUP_INTERVAL_MINUTES must be between 1 and 59.');
}

Schedule::command('access:purge-expired-challenges')
    ->cron("*/{$cleanupIntervalMinutes} * * * *");
