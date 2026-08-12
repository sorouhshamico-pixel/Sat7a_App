<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\DispatchServiceProvider;
use App\Providers\MapsServiceProvider;
use App\Providers\NotificationServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    DispatchServiceProvider::class,
    MapsServiceProvider::class,
    NotificationServiceProvider::class,
];
