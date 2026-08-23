<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dashboard currently has no container bindings.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-dashboard-read',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_dashboard.rate_limits.read_per_minute',
                    120
                )
            )->by(
                (string) (
                    $request->user()
                        ?->getAuthIdentifier()
                    ?? $request->ip()
                )
            )
        );

        $this->loadRoutesFrom(
            base_path(
                'routes/api_dashboard.php'
            )
        );
    }
}
