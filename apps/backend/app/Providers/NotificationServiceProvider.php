<?php

namespace App\Providers;

use App\Domain\Notifications\Adapters\LogSmsProvider;
use App\Domain\Notifications\Contracts\SmsProvider;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, function () {
            return match (config('services.sms.driver', 'log')) {
                // Real provider adapters are added in Phase 16 once a vendor
                // is chosen and credentials exist — see docs/SECURITY.md
                // §Secrets. Any unrecognized driver safely falls back to the
                // log adapter rather than sending nothing silently.
                default => new LogSmsProvider,
            };
        });
    }
}
