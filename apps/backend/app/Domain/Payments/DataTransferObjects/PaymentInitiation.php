<?php

namespace App\Domain\Payments\DataTransferObjects;

use App\Domain\Payments\Models\Payment;

/**
 * What App\Domain\Payments\Actions\CreatePaymentAction hands back to the
 * controller — the persisted Payment plus the gateway's (transient,
 * never stored) checkout redirect URL, when there is one.
 */
readonly class PaymentInitiation
{
    public function __construct(
        public Payment $payment,
        public ?string $redirectUrl,
    ) {}
}
