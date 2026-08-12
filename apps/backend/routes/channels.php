<?php

use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Every channel here MUST authorize against the authenticated user before
| granting access. No user should ever be able to subscribe to an order or
| driver channel they don't own or aren't assigned to (see
| docs/DISPATCH_ENGINE.md and docs/ARCHITECTURE.md §4). Kept in this one
| file rather than scattered across domain service providers — it's the
| single place to check what's reachable over a WebSocket connection.
|
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

// App\Domain\Orders\Events\OrderStatusChanged (Phase 9's dispatch
// assignment/cancellation events broadcast here too, since they all go
// through the same OrderStateMachine). Reachable by: the owning customer,
// the assigned driver, staff of the assigned provider, or platform staff
// with orders.view_all.
Broadcast::channel('orders.{orderPublicId}', function (User $user, string $orderPublicId) {
    $order = Order::query()->where('public_id', $orderPublicId)->first();

    if ($order === null) {
        return false;
    }

    if ($order->customer->user_id === $user->id) {
        return true;
    }

    if ($user->driver !== null && $order->assigned_driver_id === $user->driver->id) {
        return true;
    }

    if ($user->provider_id !== null && $order->assigned_provider_id === $user->provider_id) {
        return true;
    }

    return $user->can('orders.view_all');
});

// App\Domain\Dispatch\Events\DispatchOfferCreated — new-job push. Reachable
// only by the driver the offer was made to; there is no platform-staff
// broadcast equivalent (ops use the polled admin endpoints instead).
Broadcast::channel('drivers.{driverPublicId}', function (User $user, string $driverPublicId) {
    return $user->driver !== null && $user->driver->public_id === $driverPublicId;
});
