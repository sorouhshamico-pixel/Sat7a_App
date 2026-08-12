<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Drivers\Models\Driver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Every "my dispatch offers" endpoint resolves through this — mirrors
 * ResolvesCustomer/ResolvesProvider. There is never a {driver} route
 * parameter, so a driver can never reach another driver's offers.
 */
trait ResolvesDriver
{
    protected function resolveDriver(Request $request): Driver
    {
        $driver = $request->user()->driver;

        if ($driver === null) {
            throw new ModelNotFoundException('No driver profile is associated with this account.');
        }

        return $driver;
    }
}
