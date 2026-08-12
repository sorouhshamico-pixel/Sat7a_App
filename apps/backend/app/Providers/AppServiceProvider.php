<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Baseline API throttle; endpoints with tighter requirements define
        // their own named limiters — see docs/SECURITY.md §Rate limiting.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('otp-send', function (Request $request) {
            $phone = (string) $request->input('phone');

            return [
                Limit::perHour(5)->by('otp-send:phone:'.$phone),
                Limit::perHour(20)->by('otp-send:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $phone = (string) $request->input('phone');

            return Limit::perMinutes(5, 10)->by('otp-verify:'.$phone.':'.$request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinutes(15, 5)->by('admin-login:email:'.$email),
                Limit::perMinutes(15, 20)->by('admin-login:ip:'.$request->ip()),
            ];
        });
    }
}
