<?php

use App\Http\Controllers\Api\V1\Admin\BankAccountController as AdminBankAccountController;
use App\Http\Controllers\Api\V1\Admin\DispatchController as AdminDispatchController;
use App\Http\Controllers\Api\V1\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Api\V1\Admin\DocumentVerificationController;
use App\Http\Controllers\Api\V1\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Api\V1\Admin\LedgerController as AdminLedgerController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Api\V1\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Api\V1\Admin\TowTruckController as AdminTowTruckController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Customers\DisputeController as CustomerDisputeController;
use App\Http\Controllers\Api\V1\Customers\ReviewController as CustomerReviewController;
use App\Http\Controllers\Api\V1\Customers\SavedLocationController;
use App\Http\Controllers\Api\V1\Customers\VehicleController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\Drivers\DispatchOfferController;
use App\Http\Controllers\Api\V1\Drivers\LocationController;
use App\Http\Controllers\Api\V1\Drivers\TripController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Maps\CityController;
use App\Http\Controllers\Api\V1\Maps\MapsController;
use App\Http\Controllers\Api\V1\Orders\OrderController;
use App\Http\Controllers\Api\V1\Orders\PaymentController;
use App\Http\Controllers\Api\V1\Pricing\QuoteController;
use App\Http\Controllers\Api\V1\Providers\BankAccountController;
use App\Http\Controllers\Api\V1\Providers\LedgerController;
use App\Http\Controllers\Api\V1\Providers\ProviderController;
use App\Http\Controllers\Api\V1\Providers\ProviderDocumentController;
use App\Http\Controllers\Api\V1\Providers\ProviderDriverController;
use App\Http\Controllers\Api\V1\Providers\ProviderFleetController;
use App\Http\Controllers\Api\V1\Providers\ReviewController as ProviderReviewController;
use App\Http\Controllers\Api\V1\Providers\SettlementController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every route lives under a version prefix (/api/v1) from day one so a
| future /api/v2 can be introduced without breaking existing Next.js or
| Flutter clients (see docs/API_SPECIFICATION.md).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/cities', [CityController::class, 'index'])->name('cities.index');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        // Customer / provider-staff phone + OTP (see docs/SECURITY.md §OTP handling).
        Route::post('/otp/send', [OtpController::class, 'send'])
            ->middleware('throttle:otp-send')
            ->name('otp.send');
        Route::post('/otp/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:otp-verify')
            ->name('otp.verify');

        // Admin/platform-staff email + password, always followed by a
        // mandatory MFA step — see docs/SECURITY.md §Authentication.
        Route::post('/admin/login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:admin-login')
            ->name('admin.login');

        Route::middleware(['auth:sanctum', 'abilities:mfa-setup'])->group(function (): void {
            Route::post('/admin/mfa/setup', [TwoFactorController::class, 'setup'])->name('admin.mfa.setup');
            Route::post('/admin/mfa/confirm', [TwoFactorController::class, 'confirm'])->name('admin.mfa.confirm');
        });

        Route::post('/admin/mfa/challenge', [TwoFactorController::class, 'verifyChallenge'])
            ->middleware(['auth:sanctum', 'abilities:mfa-challenge'])
            ->name('admin.mfa.challenge');

        // Requires a fully-privileged token (abilities contains '*') — the
        // narrowly-scoped mfa-setup/mfa-challenge tokens above must never
        // be usable against general authenticated endpoints.
        Route::middleware(['auth:sanctum', 'abilities:*'])->group(function (): void {
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

            Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::delete('/sessions/{tokenId}', [SessionController::class, 'destroy'])
                ->whereNumber('tokenId')
                ->name('sessions.destroy');
            Route::post('/sessions/revoke-all', [SessionController::class, 'destroyAll'])->name('sessions.revoke-all');
        });
    });

    // Provider self-service (registration is public; everything else
    // requires a fully-privileged provider_staff token — see
    // docs/COMPLIANCE.md §Provider compliance lifecycle).
    Route::prefix('providers')->name('providers.')->group(function (): void {
        Route::post('/register', [ProviderController::class, 'register'])
            ->middleware('throttle:otp-send')
            ->name('register');

        Route::middleware(['auth:sanctum', 'abilities:*'])->group(function (): void {
            Route::get('/me', [ProviderController::class, 'show'])->name('me.show');
            Route::patch('/me', [ProviderController::class, 'update'])->name('me.update');
            Route::get('/me/documents', [ProviderDocumentController::class, 'index'])->name('me.documents.index');
            Route::post('/me/documents', [ProviderDocumentController::class, 'store'])->name('me.documents.store');

            // Fleet & drivers — gated by drivers.manage/fleet.manage
            // (granted to provider_owner and fleet_manager, not driver —
            // see docs/ROLES_PERMISSIONS.md). Always scoped to the
            // caller's own provider; there is no {provider} parameter.
            Route::middleware('can:drivers.manage')->group(function (): void {
                Route::get('/me/drivers', [ProviderDriverController::class, 'index'])->name('me.drivers.index');
                Route::post('/me/drivers', [ProviderDriverController::class, 'store'])->name('me.drivers.store');
                Route::patch('/me/drivers/{driverPublicId}/availability', [ProviderDriverController::class, 'updateAvailability'])
                    ->name('me.drivers.availability');
            });

            Route::middleware('can:fleet.manage')->group(function (): void {
                Route::get('/me/fleet', [ProviderFleetController::class, 'index'])->name('me.fleet.index');
                Route::post('/me/fleet', [ProviderFleetController::class, 'store'])->name('me.fleet.store');
                Route::get('/me/fleet/summary', [ProviderFleetController::class, 'summary'])->name('me.fleet.summary');
                Route::patch('/me/fleet/{towTruckPublicId}/driver', [ProviderFleetController::class, 'assignDriver'])
                    ->name('me.fleet.assign-driver');
                Route::patch('/me/fleet/{towTruckPublicId}/status', [ProviderFleetController::class, 'updateStatus'])
                    ->name('me.fleet.status');
            });

            // Ledger & balance — see docs/SETTLEMENT_ARCHITECTURE.md. No
            // extra permission gate: this is always the caller's own
            // provider, resolved via ResolvesProvider, same as the rest
            // of this group.
            Route::get('/me/balance', [LedgerController::class, 'balance'])->name('me.balance');
            Route::get('/me/ledger', [LedgerController::class, 'index'])->name('me.ledger.index');

            // Bank account & settlements — see
            // docs/SETTLEMENT_ARCHITECTURE.md §Bank account security. No
            // extra permission gate: always the caller's own provider,
            // and ProviderBankAccountResource itself only reveals the
            // unmasked IBAN to the owning provider or a holder of
            // settlements.view_bank_details.
            Route::get('/me/bank-account', [BankAccountController::class, 'show'])->name('me.bank-account.show');
            Route::put('/me/bank-account', [BankAccountController::class, 'update'])->name('me.bank-account.update');
            Route::get('/me/settlements', [SettlementController::class, 'index'])->name('me.settlements.index');
            Route::get('/me/settlements/{settlementPublicId}', [SettlementController::class, 'show'])->name('me.settlements.show');

            // Reviews — see docs/REVIEWS_DISPUTES.md. No extra permission
            // gate: always the caller's own provider.
            Route::get('/me/reviews', [ProviderReviewController::class, 'index'])->name('me.reviews.index');
        });
    });

    // Driver self-service — always scoped to the caller's own driver
    // profile via ResolvesDriver, never a bare {driver} parameter (see
    // docs/DISPATCH_ENGINE.md). No extra permission gate: ownership of the
    // offer is the only boundary, same shape as customers/me/... below.
    Route::prefix('drivers')->name('drivers.')->middleware(['auth:sanctum', 'abilities:*'])->group(function (): void {
        Route::get('/me/dispatch-offers', [DispatchOfferController::class, 'index'])->name('me.dispatch-offers.index');
        Route::post('/me/dispatch-offers/{offerPublicId}/accept', [DispatchOfferController::class, 'accept'])
            ->name('me.dispatch-offers.accept');
        Route::post('/me/dispatch-offers/{offerPublicId}/reject', [DispatchOfferController::class, 'reject'])
            ->name('me.dispatch-offers.reject');

        // Live location & trip progress — see docs/LIVE_LOCATION_TRACKING.md.
        Route::post('/me/location', [LocationController::class, 'store'])
            ->middleware('throttle:location-ping')
            ->name('me.location.store');
        Route::post('/me/orders/{orderPublicId}/status', [TripController::class, 'advance'])
            ->name('me.orders.status');
    });

    // Customer self-service — always scoped to the caller's own profile;
    // there is no {customer} route parameter (see
    // docs/PRODUCT_REQUIREMENTS.md §Customer profile).
    Route::prefix('customers')->name('customers.')->middleware(['auth:sanctum', 'abilities:*'])->group(function (): void {
        Route::get('/me', [CustomerController::class, 'show'])->name('me.show');
        Route::patch('/me', [CustomerController::class, 'update'])->name('me.update');
        Route::post('/me/avatar', [CustomerController::class, 'uploadAvatar'])->name('me.avatar');

        Route::get('/me/vehicles', [VehicleController::class, 'index'])->name('me.vehicles.index');
        Route::post('/me/vehicles', [VehicleController::class, 'store'])->name('me.vehicles.store');
        Route::patch('/me/vehicles/{vehiclePublicId}', [VehicleController::class, 'update'])->name('me.vehicles.update');
        Route::delete('/me/vehicles/{vehiclePublicId}', [VehicleController::class, 'destroy'])->name('me.vehicles.destroy');

        Route::get('/me/locations', [SavedLocationController::class, 'index'])->name('me.locations.index');
        Route::post('/me/locations', [SavedLocationController::class, 'store'])->name('me.locations.store');
        Route::patch('/me/locations/{locationPublicId}', [SavedLocationController::class, 'update'])->name('me.locations.update');
        Route::delete('/me/locations/{locationPublicId}', [SavedLocationController::class, 'destroy'])->name('me.locations.destroy');

        // Orders — always scoped to the caller's own customer profile via
        // ResolvesCustomer, never a bare {order} parameter (see
        // docs/ORDER_LIFECYCLE.md and docs/SECURITY.md).
        Route::get('/me/orders', [OrderController::class, 'index'])->name('me.orders.index');
        Route::post('/me/orders', [OrderController::class, 'store'])
            ->middleware('throttle:order-create')
            ->name('me.orders.store');
        Route::get('/me/orders/{orderPublicId}', [OrderController::class, 'show'])->name('me.orders.show');
        Route::get('/me/orders/{orderPublicId}/timeline', [OrderController::class, 'timeline'])->name('me.orders.timeline');
        Route::get('/me/orders/{orderPublicId}/location', [OrderController::class, 'location'])->name('me.orders.location');
        Route::post('/me/orders/{orderPublicId}/cancel', [OrderController::class, 'cancel'])->name('me.orders.cancel');

        // Payments — see docs/PAYMENT_ARCHITECTURE.md. Same ownership
        // scoping as every other me/orders/... sub-resource.
        Route::get('/me/orders/{orderPublicId}/payments', [PaymentController::class, 'index'])->name('me.orders.payments.index');
        Route::post('/me/orders/{orderPublicId}/payments', [PaymentController::class, 'store'])
            ->middleware('throttle:payment-create')
            ->name('me.orders.payments.store');

        // Reviews & disputes — see docs/REVIEWS_DISPUTES.md. Same
        // ownership scoping as every other me/orders/... sub-resource; no
        // extra permission gate needed.
        Route::post('/me/orders/{orderPublicId}/review', [CustomerReviewController::class, 'store'])->name('me.orders.review.store');
        Route::get('/me/orders/{orderPublicId}/review', [CustomerReviewController::class, 'show'])->name('me.orders.review.show');
        Route::post('/me/orders/{orderPublicId}/dispute', [CustomerDisputeController::class, 'store'])->name('me.orders.dispute.store');
        Route::get('/me/disputes', [CustomerDisputeController::class, 'index'])->name('me.disputes.index');
        Route::get('/me/disputes/{disputePublicId}', [CustomerDisputeController::class, 'show'])->name('me.disputes.show');
    });

    // Shared document download — access is permission/ownership-checked
    // inside the controller (see docs/SECURITY.md §File uploads).
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware(['auth:sanctum', 'abilities:*'])
        ->name('documents.download');

    // Public (a guest builds a quote before authenticating — see
    // docs/PRODUCT_REQUIREMENTS.md), rate-limited to control third-party
    // API cost (see docs/ARCHITECTURE.md §6).
    Route::prefix('maps')->name('maps.')->middleware('throttle:maps')->group(function (): void {
        Route::post('/geocode', [MapsController::class, 'geocode'])->name('geocode');
        Route::post('/reverse-geocode', [MapsController::class, 'reverseGeocode'])->name('reverse-geocode');
        Route::get('/places/autocomplete', [MapsController::class, 'autocomplete'])->name('places.autocomplete');
        Route::get('/places/{placeId}', [MapsController::class, 'placeDetails'])->name('places.details');
        Route::post('/route', [MapsController::class, 'route'])->name('route');
    });

    // Public — a guest builds a quote before authenticating (see
    // docs/PRODUCT_REQUIREMENTS.md).
    Route::post('/pricing/quote', [QuoteController::class, 'store'])
        ->middleware('throttle:quote')
        ->name('pricing.quote');

    // Public — the payment gateway itself calls this; trust comes from
    // signature verification inside the handler, never Sanctum (see
    // docs/PAYMENT_ARCHITECTURE.md §Webhooks).
    Route::post('/webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:webhook')
        ->name('webhooks.payments');

    // Admin/platform management. Fully-privileged token required, plus the
    // specific permission for each action (see docs/ROLES_PERMISSIONS.md).
    // Role changes are audited without exception — see
    // App\Domain\Authorization\Actions\AssignRoleAction/RevokeRoleAction.
    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'abilities:*'])->group(function (): void {
        Route::middleware('can:roles.manage')->group(function (): void {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

            Route::get('/users/{user}/roles', [UserRoleController::class, 'index'])->name('users.roles.index');
            Route::post('/users/{user}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
            Route::delete('/users/{user}/roles/{roleName}', [UserRoleController::class, 'destroy'])->name('users.roles.destroy');
        });

        Route::middleware('can:providers.view')->group(function (): void {
            Route::get('/providers', [AdminProviderController::class, 'index'])->name('providers.index');
            Route::get('/providers/{provider}', [AdminProviderController::class, 'show'])->name('providers.show');
        });

        Route::post('/providers/{provider}/approve', [AdminProviderController::class, 'approve'])
            ->middleware('can:providers.approve')
            ->name('providers.approve');
        Route::post('/providers/{provider}/reject', [AdminProviderController::class, 'reject'])
            ->middleware('can:providers.approve')
            ->name('providers.reject');
        Route::post('/providers/{provider}/suspend', [AdminProviderController::class, 'suspend'])
            ->middleware('can:providers.suspend')
            ->name('providers.suspend');

        Route::middleware('can:documents.verify')->group(function (): void {
            Route::post('/documents/{document}/verify', [DocumentVerificationController::class, 'verify'])->name('documents.verify');
            Route::post('/documents/{document}/reject', [DocumentVerificationController::class, 'reject'])->name('documents.reject');
        });

        Route::post('/drivers/{driver}/suspend', [AdminDriverController::class, 'suspend'])
            ->middleware('can:drivers.suspend')
            ->name('drivers.suspend');

        Route::post('/tow-trucks/{towTruck}/suspend', [AdminTowTruckController::class, 'suspend'])
            ->middleware('can:fleet.suspend')
            ->name('tow-trucks.suspend');

        Route::get('/pricing/versions', [AdminPricingController::class, 'index'])
            ->middleware('can:pricing.view')
            ->name('pricing.versions.index');
        Route::post('/pricing/versions', [AdminPricingController::class, 'store'])
            ->middleware('can:pricing.update')
            ->name('pricing.versions.store');
        Route::post('/pricing/versions/{pricingRuleVersion}/activate', [AdminPricingController::class, 'activate'])
            ->middleware('can:pricing.update')
            ->name('pricing.versions.activate');

        Route::middleware('can:orders.view_all')->group(function (): void {
            Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/dispatch-offers', [AdminDispatchController::class, 'offers'])->name('orders.dispatch-offers');
            Route::get('/orders/{order}/location', [AdminOrderController::class, 'location'])->name('orders.location');
        });
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])
            ->middleware('can:orders.cancel')
            ->name('orders.cancel');

        // Operations dispatch override — see docs/DISPATCH_ENGINE.md
        // §Manual fallback. Reuses `orders.assign`, already seeded to
        // dispatcher/operations_manager in Phase 2.
        Route::middleware('can:orders.assign')->group(function (): void {
            Route::post('/orders/{order}/dispatch/retry', [AdminDispatchController::class, 'retry'])->name('orders.dispatch.retry');
            Route::post('/orders/{order}/dispatch/assign', [AdminDispatchController::class, 'assign'])->name('orders.dispatch.assign');
        });

        Route::middleware('can:payments.view')->group(function (): void {
            Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        });
        Route::post('/payments/{payment}/refund', [AdminPaymentController::class, 'refund'])
            ->middleware('can:payments.refund')
            ->name('payments.refund');

        Route::middleware('can:settlements.view')->group(function (): void {
            Route::get('/providers/{provider}/balance', [AdminLedgerController::class, 'balance'])->name('providers.balance');
            Route::get('/providers/{provider}/ledger', [AdminLedgerController::class, 'index'])->name('providers.ledger');
            Route::get('/settlements', [AdminSettlementController::class, 'index'])->name('settlements.index');
            Route::get('/settlements/{settlement}', [AdminSettlementController::class, 'show'])->name('settlements.show');
        });

        // Bank account & the whole settlement lifecycle (generate, then
        // submit/approve/process/paid/fail/cancel via the single generic
        // status endpoint — mirrors the AdvanceTripStatusAction pattern)
        // all reuse `settlements.approve`, the same choice already made
        // for the whole dispatch-override workflow above (see
        // docs/ROLES_PERMISSIONS.md).
        Route::middleware('can:settlements.approve')->group(function (): void {
            Route::get('/providers/{provider}/bank-account', [AdminBankAccountController::class, 'show'])->name('providers.bank-account.show');
            Route::post('/providers/{provider}/bank-account/verify', [AdminBankAccountController::class, 'verify'])->name('providers.bank-account.verify');
            Route::post('/providers/{provider}/settlements', [AdminSettlementController::class, 'store'])->name('providers.settlements.store');
            Route::post('/settlements/{settlement}/status', [AdminSettlementController::class, 'advance'])->name('settlements.status');
        });

        // Reviews — reuses `providers.view`, since this is read-only
        // information about a provider, not a distinct workflow (see
        // docs/REVIEWS_DISPUTES.md).
        Route::get('/providers/{provider}/reviews', [AdminReviewController::class, 'index'])
            ->middleware('can:providers.view')
            ->name('providers.reviews.index');

        // Disputes — a distinct sensitive workflow, gated by its own
        // permissions (customer_support/operations_manager), not reused
        // from another domain.
        Route::middleware('can:disputes.view')->group(function (): void {
            Route::get('/disputes', [AdminDisputeController::class, 'index'])->name('disputes.index');
            Route::get('/disputes/{dispute}', [AdminDisputeController::class, 'show'])->name('disputes.show');
        });
        Route::post('/disputes/{dispute}/status', [AdminDisputeController::class, 'advance'])
            ->middleware('can:disputes.manage')
            ->name('disputes.status');
    });
});
