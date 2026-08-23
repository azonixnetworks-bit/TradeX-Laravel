<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(
            PersonalAccessToken::class
        );

        RateLimiter::for(
            'api-login',
            function (
                Request $request
            ): array {
                $email = Str::lower(
                    trim(
                        (string) $request
                            ->input(
                                'email',
                                'unknown'
                            )
                    )
                );

                return [
                    Limit::perMinute(
                        (int) config(
                            'tradex_auth.rate_limits.login_per_minute',
                            5
                        )
                    )->by(
                        $email
                        .'|'
                        .$request->ip()
                    ),

                    Limit::perHour(
                        (int) config(
                            'tradex_auth.rate_limits.login_per_hour_ip',
                            30
                        )
                    )->by(
                        'ip|'
                        .$request->ip()
                    ),
                ];
            }
        );

        RateLimiter::for(
            'api-refresh',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_auth.rate_limits.refresh_per_minute',
                    30
                )
            )->by(
                $request->ip()
            )
        );

        RateLimiter::for(
            'api-share-card',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_auth.rate_limits.share_card_per_minute',
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
    }
}
