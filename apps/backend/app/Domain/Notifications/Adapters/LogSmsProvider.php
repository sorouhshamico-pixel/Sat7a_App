<?php

namespace App\Domain\Notifications\Adapters;

use App\Domain\Notifications\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;

/**
 * Default adapter when SMS_PROVIDER_DRIVER=log — used in local dev and CI
 * where no real SMS vendor credentials exist (see docs/SECURITY.md
 * §Secrets). Never used in production.
 */
class LogSmsProvider implements SmsProvider
{
    public function send(string $phoneE164, string $message): void
    {
        Log::channel('stack')->info('sms.would_send', [
            'to' => $phoneE164,
            'message' => $message,
        ]);
    }
}
