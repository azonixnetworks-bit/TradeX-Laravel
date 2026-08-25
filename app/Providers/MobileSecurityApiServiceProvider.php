<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class MobileSecurityApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // MOBILE-SEC-01 uses constructor injection only.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-mobile-security-read',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    max(
                        1,
                        (int) config(
                            'tradex_mobile_security.quick_unlock.rate_limits.read_per_minute',
                            120
                        )
                    )
                )->by(
                    (string) (
                        $request->user()?->getAuthIdentifier()
                        ?? $request->ip()
                    )
                )
        );

        RateLimiter::for(
            'api-mobile-security-change',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    max(
                        1,
                        (int) config(
                            'tradex_mobile_security.quick_unlock.rate_limits.change_per_minute',
                            10
                        )
                    )
                )->by(
                    (string) (
                        $request->user()?->getAuthIdentifier()
                        ?? $request->ip()
                    )
                )
        );

        $this->loadRoutesFrom(
            base_path(
                'routes/api_mobile_security.php'
            )
        );
    }
}
