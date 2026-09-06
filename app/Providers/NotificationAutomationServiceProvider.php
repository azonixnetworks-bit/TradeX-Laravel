<?php

namespace App\Providers;

use App\Models\DailyTradeIdea;
use App\Models\TradeIdea;
use App\Models\TradeIdeaUpdate;
use App\Models\User;
use App\Observers\DailyTradeIdeaNotificationObserver;
use App\Observers\TradeIdeaNotificationObserver;
use App\Observers\TradeIdeaUpdateNotificationObserver;
use App\Observers\UserReferralNotificationObserver;
use Illuminate\Support\ServiceProvider;

class NotificationAutomationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        TradeIdea::observe(TradeIdeaNotificationObserver::class);
        TradeIdeaUpdate::observe(TradeIdeaUpdateNotificationObserver::class);
        DailyTradeIdea::observe(DailyTradeIdeaNotificationObserver::class);
        User::observe(UserReferralNotificationObserver::class);
    }
}
