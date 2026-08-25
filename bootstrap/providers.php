<?php

use App\Providers\AccountSettingsApiServiceProvider;
use App\Providers\AffiliateApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\KycApiServiceProvider;
use App\Providers\NotificationApiServiceProvider;
use App\Providers\PaymentApiServiceProvider;

return [
    AppServiceProvider::class,
    DashboardServiceProvider::class,
    AffiliateApiServiceProvider::class,
    NotificationApiServiceProvider::class,
    AccountSettingsApiServiceProvider::class,
    PaymentApiServiceProvider::class,
    KycApiServiceProvider::class,
];
