<?php

namespace App\Domain\Orders\Enums;

/**
 * `card` is reserved for Phase 12 (Payments) — every order today is
 * created with `cash` since no payment gateway integration exists yet.
 */
enum OrderPaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
}
