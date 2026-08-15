<?php

namespace App\Domain\Notifications\Adapters;

use App\Domain\Notifications\Contracts\EmailProvider;
use Illuminate\Support\Facades\Log;

/**
 * Default adapter when EMAIL_PROVIDER_DRIVER=log — used in local dev and CI
 * where no real email vendor credentials exist. Never used in production.
 */
class LogEmailProvider implements EmailProvider
{
    public function send(string $email, string $subject, string $body): void
    {
        Log::channel('stack')->info('email.would_send', [
            'to' => $email,
            'subject' => $subject,
            'body' => $body,
        ]);
    }
}
