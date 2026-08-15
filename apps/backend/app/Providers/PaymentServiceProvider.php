<?php

namespace App\Providers;

use App\Domain\Payments\Adapters\Fake\FakePaymentGateway;
use App\Domain\Payments\Contracts\PaymentGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the default outbound payment gateway based on
 * `PAYMENT_GATEWAY_DRIVER`. No real gateway account exists yet (see
 * docs/SECURITY.md §Secrets), so this always resolves to the fake
 * adapter today; a real driver (moyasar/tap/hyperpay) is added here once
 * credentials exist, matching the SMS/Maps provider pattern. Any
 * unrecognized driver name safely falls back to the fake adapter rather
 * than failing to boot.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function () {
            return match (config('services.payments.driver', 'fake')) {
                default => new FakePaymentGateway,
            };
        });
    }
}
