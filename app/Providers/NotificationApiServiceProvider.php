<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class NotificationApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-10 currently has no custom container bindings.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-notifications-read',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_notifications_api.rate_limits.read_per_minute',
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
            'api-notifications-write',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_notifications_api.rate_limits.write_per_minute',
                    30
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
                'routes/api_notifications.php'
            )
        );
    }
}
