<?php

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
});
