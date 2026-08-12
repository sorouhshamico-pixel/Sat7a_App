<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// See docs/COMPLIANCE.md §Expiry.
Schedule::command('compliance:check-document-expiry')->dailyAt('02:00');
