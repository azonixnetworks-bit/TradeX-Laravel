<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AffiliateApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-09 currently has no custom container bindings.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-affiliate-read',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_affiliate_api.rate_limits.read_per_minute',
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

        RateLimiter::for(
            'api-affiliate-share',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_affiliate_api.rate_limits.share_per_minute',
                    20
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
                'routes/api_affiliate.php'
            )
        );
    }
}
