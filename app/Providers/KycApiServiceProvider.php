<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class KycApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-13 uses constructor injection only.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-kyc-read',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    max(
                        1,
                        (int) config(
                            'tradex_kyc.rate_limits.read_per_minute',
                            60
                        )
                    )
                )->by(
                    $this->rateLimitKey(
                        $request
                    )
                )
        );

        RateLimiter::for(
            'api-kyc-submit',
            function (
                Request $request
            ): array {
                $key = $this->rateLimitKey(
                    $request
                );

                return [
                    Limit::perMinute(
                        max(
                            1,
                            (int) config(
                                'tradex_kyc.rate_limits.submit_per_minute',
                                3
                            )
                        )
                    )->by(
                        'kyc-submit-minute|'
                        .$key
                    ),

                    Limit::perHour(
                        max(
                            1,
                            (int) config(
                                'tradex_kyc.rate_limits.submit_per_hour',
                                10
                            )
                        )
                    )->by(
                        'kyc-submit-hour|'
                        .$key
                    ),
                ];
            }
        );

        $this->loadRoutesFrom(
            base_path(
                'routes/api_kyc.php'
            )
        );
    }

    private function rateLimitKey(
        Request $request
    ): string {
        return (string) (
            $request->user()
                ?->getAuthIdentifier()
            ?? $request->ip()
        );
    }
}
