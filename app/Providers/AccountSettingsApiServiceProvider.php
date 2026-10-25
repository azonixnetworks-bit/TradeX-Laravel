<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AccountSettingsApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-11 currently has no custom container bindings.
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-account-settings-read',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config('tradex_account_settings_api.rate_limits.read_per_minute', 120)
            )->by(
                (string) ($request->user()?->getAuthIdentifier() ?? $request->ip())
            )
        );

        RateLimiter::for(
            'api-account-settings-sensitive',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config('tradex_account_settings_api.rate_limits.sensitive_per_minute', 10)
            )->by(
                (string) ($request->user()?->getAuthIdentifier() ?? $request->ip())
            )
        );

        RateLimiter::for(
            'api-account-settings-upload',
            function (Request $request): array {
                $key = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

                return [
                    Limit::perMinute(
                        (int) config('tradex_account_settings_api.rate_limits.photo_uploads_per_minute', 2)
                    )->by('profile-photo-minute|'.$key),
                    Limit::perHour(
                        (int) config('tradex_account_settings_api.rate_limits.photo_uploads_per_hour', 5)
                    )->by('profile-photo-hour|'.$key),
                ];
            }
        );

        $this->loadRoutesFrom(base_path('routes/api_account_settings.php'));
    }
}
