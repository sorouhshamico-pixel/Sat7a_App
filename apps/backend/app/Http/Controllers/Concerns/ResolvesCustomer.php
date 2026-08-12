<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Customers\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Every customer-owned resource (profile, vehicles, saved locations)
 * resolves through this — mirrors
 * App\Http\Controllers\Concerns\ResolvesProvider. There is never a
 * {customer} route parameter, so cross-customer access isn't reachable
 * through these endpoints at all.
 */
trait ResolvesCustomer
{
    protected function resolveCustomer(Request $request): Customer
    {
        $customer = $request->user()->customer;

        if ($customer === null) {
            throw new ModelNotFoundException('No customer profile is associated with this account.');
        }

        return $customer;
    }
}
