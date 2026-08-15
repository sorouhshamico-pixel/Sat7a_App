<?php

namespace App\Domain\Notifications\Adapters;

use App\Domain\Notifications\Contracts\PushProvider;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Default adapter when PUSH_PROVIDER_DRIVER=log — used in local dev and CI
 * where no real push vendor (FCM/APNs) credentials exist. Never used in
 * production.
 */
class LogPushProvider implements PushProvider
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        Log::channel('stack')->info('push.would_send', [
            'to' => $user->public_id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
