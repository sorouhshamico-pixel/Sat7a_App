<?php

use App\Http\Controllers\Api\V1\Admin\DocumentVerificationController;
use App\Http\Controllers\Api\V1\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Api\V1\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\TowTruckController as AdminTowTruckController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Customers\SavedLocationController;
use App\Http\Controllers\Api\V1\Customers\VehicleController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Maps\CityController;
use App\Http\Controllers\Api\V1\Maps\MapsController;
use App\Http\Controllers\Api\V1\Orders\OrderController;
use App\Http\Controllers\Api\V1\Pricing\QuoteController;
use App\Http\Controllers\Api\V1\Providers\ProviderController;
use App\Http\Controllers\Api\V1\Providers\ProviderDocumentController;
use App\Http\Controllers\Api\V1\Providers\ProviderDriverController;
use App\Http\Controllers\Api\V1\Providers\ProviderFleetController;
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
        });
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
        Route::post('/me/orders/{orderPublicId}/cancel', [OrderController::class, 'cancel'])->name('me.orders.cancel');
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
        });
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])
            ->middleware('can:orders.cancel')
            ->name('orders.cancel');
    });
});
