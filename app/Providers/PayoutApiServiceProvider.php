<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PayoutApiServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        RateLimiter::for('api-payouts-read',fn(Request $request):Limit=>Limit::perMinute(max(1,(int)config('tradex_payouts.rate_limits.read_per_minute',60)))->by((string)($request->user()?->getAuthIdentifier()??$request->ip())));
        RateLimiter::for('api-payouts-create',function(Request $request):array{ $key=(string)($request->user()?->getAuthIdentifier()??$request->ip()); return [Limit::perMinute(max(1,(int)config('tradex_payouts.rate_limits.create_per_minute',5)))->by('payout-create-minute|'.$key),Limit::perHour(max(1,(int)config('tradex_payouts.rate_limits.create_per_hour',10)))->by('payout-create-hour|'.$key)]; });
        $this->loadRoutesFrom(base_path('routes/api_payouts.php'));
    }
}
