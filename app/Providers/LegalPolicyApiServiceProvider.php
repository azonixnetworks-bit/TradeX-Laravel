<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class LegalPolicyApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-17 uses constructor injection only.
    }

    public function boot(): void
    {
        /*
         * IMPORTANT FROZEN BOUNDARY:
         *
         * ADMIN-17 intentionally owns tradex_legal.acceptance_runtime and keeps
         * that marker as NOT_WIRED. API-17 must not mutate the ADMIN-17 config
         * namespace at boot time because doing so changes frozen admin behavior.
         *
         * API-17 exposes its own runtime state through tradex_legal_api and its
         * customer-facing responses instead.
         */

        RateLimiter::for(
            'api-legal-public-read',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config('tradex_legal_api.rate_limits.public_read_per_minute', 120)
            )->by('legal-public|'.$request->ip())
        );

        RateLimiter::for(
            'api-legal-auth-read',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config('tradex_legal_api.rate_limits.auth_read_per_minute', 120)
            )->by(
                'legal-auth|'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip())
            )
        );

        RateLimiter::for(
            'api-legal-accept',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config('tradex_legal_api.rate_limits.accept_per_minute', 10)
            )->by(
                'legal-accept|'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip())
            )
        );

        $this->loadRoutesFrom(
            base_path('routes/api_legal.php')
        );
    }
}
