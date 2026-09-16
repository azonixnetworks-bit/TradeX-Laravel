<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EconomicNewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api-economic-news-read', function (Request $request): Limit {
            return Limit::perMinute(90)
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api_economic_news.php'));
    }
}
