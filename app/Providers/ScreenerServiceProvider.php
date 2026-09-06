<?php

namespace App\Providers;

use App\Contracts\Screener\MarketDataProvider;
use App\Services\Screener\Providers\TwelveDataForexProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ScreenerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MarketDataProvider::class,
            TwelveDataForexProvider::class
        );
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-screener-read',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    (int) config(
                        'tradex_screener.rate_limits.read_per_minute',
                        60
                    )
                )->by(
                    (string) (
                        $request->user()
                            ?->getAuthIdentifier()
                        ?? $request->ip()
                    )
                )
        );

        Route::middleware('api')
            ->prefix('api')
            ->group(
                base_path(
                    'routes/api_screener.php'
                )
            );
    }
}
