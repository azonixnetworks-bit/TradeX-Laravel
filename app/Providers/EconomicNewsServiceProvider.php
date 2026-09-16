<?php

namespace App\Providers;

use App\Services\EconomicNews\Contracts\EconomicCalendarProvider;
use App\Services\EconomicNews\Providers\ForexFactoryJsonCalendarProvider;
use App\Services\EconomicNews\Providers\TradingEconomicsCalendarProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class EconomicNewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EconomicCalendarProvider::class, function (): EconomicCalendarProvider {
            $provider = strtolower(trim((string) config(
                'economic_news.sync.provider',
                'forex_factory_json'
            )));

            return match ($provider) {
                'forex_factory_json' => new ForexFactoryJsonCalendarProvider(),
                'trading_economics' => new TradingEconomicsCalendarProvider(),
                default => throw new InvalidArgumentException(
                    sprintf('Unsupported economic news provider [%s].', $provider)
                ),
            };
        });
    }

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
