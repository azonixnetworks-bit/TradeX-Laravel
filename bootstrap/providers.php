<?php

use App\Providers\AccountSettingsApiServiceProvider;
use App\Providers\AffiliateApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CopyTradingServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\EconomicNewsServiceProvider;
use App\Providers\FundedAccountAdvisorApiServiceProvider;
use App\Providers\InvestmentServiceProvider;
use App\Providers\KycApiServiceProvider;
use App\Providers\LegalPolicyApiServiceProvider;
use App\Providers\MobileBootstrapApiServiceProvider;
use App\Providers\MobileSecurityApiServiceProvider;
use App\Providers\NotificationApiServiceProvider;
use App\Providers\NotificationAutomationServiceProvider;
use App\Providers\PasswordRecoveryApiServiceProvider;
use App\Providers\PaymentApiServiceProvider;
use App\Providers\PayoutApiServiceProvider;
use App\Providers\ProductServiceProvider;
use App\Providers\PushNotificationApiServiceProvider;
use App\Providers\RuntimeTranslationApiServiceProvider;
use App\Providers\ScreenerServiceProvider;
use App\Providers\SocialAuthApiServiceProvider;
use App\Providers\TradeJournalServiceProvider;
use App\Providers\TradingRoutineServiceProvider;
use App\Providers\WhatsAppApiServiceProvider;

return [
    AppServiceProvider::class,
    DashboardServiceProvider::class,
    InvestmentServiceProvider::class,
    AffiliateApiServiceProvider::class,
    NotificationApiServiceProvider::class,
    NotificationAutomationServiceProvider::class,
    AccountSettingsApiServiceProvider::class,
    PaymentApiServiceProvider::class,
    ProductServiceProvider::class,
    KycApiServiceProvider::class,
    PayoutApiServiceProvider::class,
    PasswordRecoveryApiServiceProvider::class,
    MobileSecurityApiServiceProvider::class,
    SocialAuthApiServiceProvider::class,
    LegalPolicyApiServiceProvider::class,
    RuntimeTranslationApiServiceProvider::class,
    MobileBootstrapApiServiceProvider::class,
    PushNotificationApiServiceProvider::class,
    CopyTradingServiceProvider::class,
    TradeJournalServiceProvider::class,
    FundedAccountAdvisorApiServiceProvider::class,
    TradingRoutineServiceProvider::class,
    EconomicNewsServiceProvider::class,
    ScreenerServiceProvider::class,
    WhatsAppApiServiceProvider::class,
];
