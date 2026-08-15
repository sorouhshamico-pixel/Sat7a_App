<?php

namespace App\Domain\Notifications\Contracts;

use App\Models\User;

/**
 * See App\Domain\Notifications\Contracts\SmsProvider's docblock — same
 * "business code never talks to a vendor SDK directly" rule applies to
 * every channel in this domain.
 */
interface PushProvider
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(User $user, string $title, string $body, array $data = []): void;
}
