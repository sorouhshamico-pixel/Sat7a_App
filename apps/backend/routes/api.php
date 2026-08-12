<?php

use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\HealthController;
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
    });
});
