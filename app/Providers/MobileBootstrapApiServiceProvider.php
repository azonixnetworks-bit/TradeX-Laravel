<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class MobileBootstrapApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-19 uses constructor injection only.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-mobile-bootstrap',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config(
                    'tradex_mobile_bootstrap.rate_limits.read_per_minute',
                    120
                )
            )->by('mobile-bootstrap|'.$request->ip())
        );

        $this->loadRoutesFrom(
            base_path('routes/api_bootstrap.php')
        );
    }
}
