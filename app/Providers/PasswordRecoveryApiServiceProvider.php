<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PasswordRecoveryApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Constructor injection is sufficient for API-15.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-password-recovery-start',
            function (Request $request): array {
                $email = mb_strtolower(
                    trim(
                        (string) $request->input('email')
                    )
                );

                $identityKey = hash(
                    'sha256',
                    $request->ip().'|'.$email
                );

                return [
                    Limit::perMinute(
                        max(
                            1,
                            (int) config(
                                'tradex_password_recovery.rate_limits.start_per_minute',
                                3
                            )
                        )
                    )->by(
                        'password-recovery-start-minute|'
                        .$identityKey
                    ),
                    Limit::perHour(
                        max(
                            1,
                            (int) config(
                                'tradex_password_recovery.rate_limits.start_per_hour_ip',
                                20
                            )
                        )
                    )->by(
                        'password-recovery-start-hour|'
                        .$request->ip()
                    ),
                ];
            }
        );

        RateLimiter::for(
            'api-password-recovery-verify',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    max(
                        1,
                        (int) config(
                            'tradex_password_recovery.rate_limits.verify_per_minute',
                            10
                        )
                    )
                )->by(
                    'password-recovery-verify|'
                    .(
                        $request->input('recovery_id')
                        ?: $request->ip()
                    )
                )
        );

        RateLimiter::for(
            'api-password-recovery-resend',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    max(
                        1,
                        (int) config(
                            'tradex_password_recovery.rate_limits.resend_per_minute',
                            3
                        )
                    )
                )->by(
                    'password-recovery-resend|'
                    .(
                        $request->input('recovery_id')
                        ?: $request->ip()
                    )
                )
        );

        RateLimiter::for(
            'api-password-recovery-reset',
            fn (Request $request): Limit =>
                Limit::perMinute(
                    max(
                        1,
                        (int) config(
                            'tradex_password_recovery.rate_limits.reset_per_minute',
                            5
                        )
                    )
                )->by(
                    'password-recovery-reset|'
                    .(
                        $request->input('recovery_id')
                        ?: $request->ip()
                    )
                )
        );

        $this->loadRoutesFrom(
            base_path('routes/api_password_recovery.php')
        );
    }
}
