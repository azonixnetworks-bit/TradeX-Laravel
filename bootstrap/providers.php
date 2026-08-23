<?php

use App\Providers\AffiliateApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\NotificationApiServiceProvider;

return [
    AppServiceProvider::class,
    DashboardServiceProvider::class,
    AffiliateApiServiceProvider::class,
    NotificationApiServiceProvider::class,
];
