<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\MapsServiceProvider;
use App\Providers\NotificationServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    MapsServiceProvider::class,
    NotificationServiceProvider::class,
];
