<?php

namespace App\Domain\Notifications\Contracts;

interface WhatsappProvider
{
    public function send(string $phoneE164, string $message): void;
}
