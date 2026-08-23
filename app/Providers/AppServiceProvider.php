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

        RateLimiter::for(
            'api-register-start',
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
                            'tradex_registration.rate_limits.start_per_minute',
                            3
                        )
                    )->by(
                        $email
                        .'|'
                        .$request->ip()
                    ),

                    Limit::perHour(
                        (int) config(
                            'tradex_registration.rate_limits.start_per_hour_ip',
                            20
                        )
                    )->by(
                        'register-ip|'
                        .$request->ip()
                    ),
                ];
            }
        );

        RateLimiter::for(
            'api-register-otp-send',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_registration.rate_limits.otp_send_per_minute',
                    3
                )
            )->by(
                (string) $request->input(
                    'registration_id',
                    $request->ip()
                )
                .'|'
                .$request->ip()
            )
        );

        RateLimiter::for(
            'api-register-verify',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_registration.rate_limits.verify_per_minute',
                    10
                )
            )->by(
                (string) $request->input(
                    'registration_id',
                    $request->ip()
                )
                .'|'
                .$request->ip()
            )
        );

        RateLimiter::for(
            'api-register-totp',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_registration.rate_limits.totp_per_minute',
                    10
                )
            )->by(
                (string) $request->input(
                    'registration_id',
                    $request->ip()
                )
                .'|'
                .$request->ip()
            )
        );

        RateLimiter::for(
            'api-profile-read',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_profile.rate_limits.read_per_minute',
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

        RateLimiter::for(
            'api-profile-write',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_profile.rate_limits.write_per_minute',
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
            'api-profile-sensitive',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_profile.rate_limits.sensitive_per_minute',
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
            'api-profile-contact',
            fn (
                Request $request
            ): Limit => Limit::perMinute(
                (int) config(
                    'tradex_profile.rate_limits.contact_per_minute',
                    8
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
