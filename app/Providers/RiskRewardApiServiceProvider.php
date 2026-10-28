<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RiskRewardApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $key = static fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        RateLimiter::for('api-risk-reward-read', fn (Request $request): Limit => Limit::perMinute(90)->by($key($request)));
        RateLimiter::for('api-risk-reward-calculate', fn (Request $request): Limit => Limit::perMinute(30)->by($key($request)));
        RateLimiter::for('api-risk-reward-write', fn (Request $request): Limit => Limit::perMinute(20)->by($key($request)));

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api_risk_reward.php'));
    }
}
