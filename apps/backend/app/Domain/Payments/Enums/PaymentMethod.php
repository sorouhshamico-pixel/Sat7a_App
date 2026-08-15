<?php

namespace App\Domain\Payments\Enums;

/**
 * See docs/PAYMENT_ARCHITECTURE.md §Methods. Deliberately separate from
 * App\Domain\Orders\Enums\OrderPaymentMethod — that's the coarse cash-or-
 * card choice made at order creation (Phase 8); this is the specific
 * card network (or `cash`) recorded once an actual payment attempt exists.
 */
enum PaymentMethod: string
{
    case Mada = 'mada';
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case ApplePay = 'apple_pay';
    case Cash = 'cash';
}
