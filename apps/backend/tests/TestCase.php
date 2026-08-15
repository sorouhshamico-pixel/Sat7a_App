<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's `sanctum` guard (a RequestGuard) caches the resolved user
     * for the lifetime of the test's app container, so any test that
     * authenticates as more than one identity in sequence must force a
     * fresh guard resolution between requests — otherwise it silently
     * keeps "logged in" as whichever identity was resolved first, which
     * never happens in real usage (each real request gets a fresh
     * container). Use this instead of `withToken()` whenever a test makes
     * more than one authenticated request as different users/tokens.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function actingAsToken(string $method, string $token, string $uri, array $data = [], array $headers = []): TestResponse
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeaders($headers)->json($method, $uri, $data);
    }

    protected function tokenFor(User $user, array $abilities = ['*']): string
    {
        return $user->createToken('test', $abilities)->plainTextToken;
    }
}
