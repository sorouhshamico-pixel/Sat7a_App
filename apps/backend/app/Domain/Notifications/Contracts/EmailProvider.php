<?php

namespace App\Domain\Notifications\Contracts;

interface EmailProvider
{
    public function send(string $email, string $subject, string $body): void;
}
