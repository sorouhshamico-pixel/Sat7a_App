<?php

namespace App\Providers;

use App\Domain\Dispatch\Listeners\StartDispatchListener;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderCreated;
use App\Domain\Orders\Listeners\LogOrderLifecycleListener;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        // Public (guest quote flow doesn't require auth — see
        // docs/PRODUCT_REQUIREMENTS.md), but bounded to control third-party
        // API cost (see docs/ARCHITECTURE.md §6 Map cost optimization).
        RateLimiter::for('maps', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Public quote endpoint — same reasoning as `maps` above.
        RateLimiter::for('quote', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Order creation — deliberately stricter than the general API
        // limit (see docs/SECURITY.md §Rate limiting baseline).
        RateLimiter::for('order-create', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->user()?->id ?: $request->ip());
        });

        // A driver's app pings this frequently while on a trip — looser
        // than the general API limit, but still bounded (see
        // docs/LIVE_LOCATION_TRACKING.md).
        RateLimiter::for('location-ping', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Payment creation — deliberately stricter than the general API
        // limit, same reasoning as `order-create` (see
        // docs/PAYMENT_ARCHITECTURE.md).
        RateLimiter::for('payment-create', function (Request $request) {
            return Limit::perMinutes(10, 10)->by($request->user()?->id ?: $request->ip());
        });

        // Public, unauthenticated — a real gateway can retry deliveries;
        // generous but still bounded against abuse (see
        // docs/PAYMENT_ARCHITECTURE.md §Webhooks).
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        Event::listen(OrderCreated::class, [LogOrderLifecycleListener::class, 'handleCreated']);
        Event::listen(OrderCancelled::class, [LogOrderLifecycleListener::class, 'handleCancelled']);
        Event::listen(OrderCreated::class, StartDispatchListener::class);
    }
}
