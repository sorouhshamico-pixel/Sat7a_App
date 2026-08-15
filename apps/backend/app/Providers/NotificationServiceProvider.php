<?php

namespace App\Providers;

use App\Domain\Notifications\Adapters\LogEmailProvider;
use App\Domain\Notifications\Adapters\LogPushProvider;
use App\Domain\Notifications\Adapters\LogSmsProvider;
use App\Domain\Notifications\Adapters\LogWhatsappProvider;
use App\Domain\Notifications\Contracts\EmailProvider;
use App\Domain\Notifications\Contracts\PushProvider;
use App\Domain\Notifications\Contracts\SmsProvider;
use App\Domain\Notifications\Contracts\WhatsappProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Every channel defaults to its Log adapter — no real vendor (SMS, push,
 * email, WhatsApp) has credentials yet (see docs/SECURITY.md §Secrets).
 * Any unrecognized driver value safely falls back to the log adapter
 * rather than sending nothing silently. Swapping in a real vendor later is
 * a one-line change here, never touching App\Domain\Notifications\Actions\
 * SendNotificationAction or any of its callers.
 */
class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, function () {
            return match (config('services.sms.driver', 'log')) {
                default => new LogSmsProvider,
            };
        });

        $this->app->bind(PushProvider::class, function () {
            return match (config('services.push.driver', 'log')) {
                default => new LogPushProvider,
            };
        });

        $this->app->bind(EmailProvider::class, function () {
            return match (config('services.email.driver', 'log')) {
                default => new LogEmailProvider,
            };
        });

        $this->app->bind(WhatsappProvider::class, function () {
            return match (config('services.whatsapp.driver', 'log')) {
                default => new LogWhatsappProvider,
            };
        });
    }
}
