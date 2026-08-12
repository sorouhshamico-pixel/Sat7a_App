<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Providers\Models\Provider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Every "my provider" endpoint (profile, documents, fleet, drivers) uses
 * this same resolution — the owner, a fleet manager, and a driver all
 * reach their provider the same way via `users.provider_id` (see
 * docs/DATABASE_SCHEMA.md). There is never a {provider} route parameter
 * on these endpoints, so cross-provider access isn't reachable through
 * them at all — not just permission-checked away.
 */
trait ResolvesProvider
{
    protected function resolveProvider(Request $request): Provider
    {
        $provider = $request->user()->provider;

        if ($provider === null) {
            throw new ModelNotFoundException('No provider is associated with this account.');
        }

        return $provider;
    }
}
