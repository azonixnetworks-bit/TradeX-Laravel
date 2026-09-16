<?php

namespace App\Providers;

use App\Contracts\Trading\TradingRoutineWhatsAppGateway;
use App\Services\Trading\MetaTradingRoutineWhatsAppGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TradingRoutineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TradingRoutineWhatsAppGateway::class,
            MetaTradingRoutineWhatsAppGateway::class
        );
    }

    public function boot(): void
    {
        RateLimiter::for('api-trading-routine-read', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('api-trading-routine-write', function (Request $request): Limit {
            return Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api_trading_routine.php'));
    }
}
