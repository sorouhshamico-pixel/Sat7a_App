<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Every channel here MUST authorize against the authenticated user before
| granting access. No user should ever be able to subscribe to an order,
| trip, or provider channel they don't own or aren't assigned to
| (see docs/DISPATCH_ENGINE.md and docs/ARCHITECTURE.md §4).
|
| Domain-specific channel authorization callbacks are registered by their
| owning domain's service provider as those domains are implemented
| (Orders, Trips, Dispatch) rather than centralized here.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, int $id) {
    return (int) $user->id === $id;
});
