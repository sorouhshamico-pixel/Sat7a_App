<?php

namespace App\Domain\Notifications\Adapters;

use App\Domain\Notifications\Contracts\WhatsappProvider;
use Illuminate\Support\Facades\Log;

/**
 * Default adapter when WHATSAPP_PROVIDER_DRIVER=log — used in local dev and
 * CI where no real WhatsApp Business API credentials exist. Never used in
 * production.
 */
class LogWhatsappProvider implements WhatsappProvider
{
    public function send(string $phoneE164, string $message): void
    {
        Log::channel('stack')->info('whatsapp.would_send', [
            'to' => $phoneE164,
            'message' => $message,
        ]);
    }
}
