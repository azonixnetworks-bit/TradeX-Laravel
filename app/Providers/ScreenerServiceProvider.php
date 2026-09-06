<?php

namespace App\Providers;

use App\Contracts\Screener\DerivWebSocketTransport;
use App\Services\Screener\IndicatorEngine;
use App\Services\Screener\Providers\BinanceCryptoProvider;
use App\Services\Screener\Providers\DerivMarketDataProvider;
use App\Services\Screener\Providers\TwelveDataForexProvider;
use App\Services\Screener\Providers\TwelveDataStocksProvider;
use App\Services\Screener\ScreenerEngine;
use App\Services\Screener\StocksScreenerEngine;
use App\Services\Screener\Transports\NativeDerivWebSocketTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ScreenerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            DerivWebSocketTransport::class,
            NativeDerivWebSocketTransport::class
        );

        $this->app->tag(
            [
                TwelveDataForexProvider::class,
                BinanceCryptoProvider::class,
                DerivMarketDataProvider::class,
                TwelveDataStocksProvider::class,
            ],
            'tradex.screener.providers'
        );

        $this->app->singleton(
            ScreenerEngine::class,
            fn ($app): ScreenerEngine => new StocksScreenerEngine(
                $app->tagged(
                    'tradex.screener.providers'
                ),
                $app->make(IndicatorEngine::class)
            )
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
