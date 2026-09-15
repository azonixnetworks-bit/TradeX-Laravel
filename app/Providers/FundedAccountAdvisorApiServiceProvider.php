<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FundedAccountAdvisorApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api-funded-advisor-read', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('api-funded-advisor-write', function (Request $request): Limit {
            return Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api_funded_account_advisor.php'));
    }
}
