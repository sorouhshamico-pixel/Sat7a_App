<?php

namespace App\Domain\Notifications\Contracts;

/**
 * Business/domain code never talks to an SMS vendor SDK directly — it
 * depends on this interface so the real provider can be swapped without
 * touching OTP or notification logic (see docs/ARCHITECTURE.md §Notifications
 * and docs/SECURITY.md §Secrets).
 */
interface SmsProvider
{
    public function send(string $phoneE164, string $message): void;
}
