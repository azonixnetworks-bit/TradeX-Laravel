<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PaymentApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-12 uses constructor injection only.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-payments-read',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    (int) config(
                        'tradex_payments.rate_limits.read_per_minute',
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
            'api-payments-create',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    (int) config(
                        'tradex_payments.rate_limits.create_per_minute',
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

        RateLimiter::for(
            'api-payments-capture',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    (int) config(
                        'tradex_payments.rate_limits.capture_per_minute',
                        10
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
            'api-payments-webhook',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    (int) config(
                        'tradex_payments.rate_limits.webhook_per_minute',
                        120
                    )
                )->by(
                    (string) $request->ip()
                )
        );

        $this->loadRoutesFrom(
            base_path(
                'routes/api_payments.php'
            )
        );
    }
}
