<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SocialAuthApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Constructor injection is sufficient for API-16.
    }

    public function boot(): void
    {
        RateLimiter::for('api-social-challenge', fn (Request $request): Limit =>
            Limit::perMinute((int) config('tradex_social_auth.rate_limits.challenge_per_minute', 10))
                ->by('social-challenge|'.$request->ip().'|'.$request->route('provider'))
        );

        RateLimiter::for('api-social-login', fn (Request $request): Limit =>
            Limit::perMinute((int) config('tradex_social_auth.rate_limits.login_per_minute', 5))
                ->by('social-login|'.$request->ip().'|'.$request->route('provider'))
        );

        RateLimiter::for('api-social-onboard', fn (Request $request): Limit =>
            Limit::perMinute((int) config('tradex_social_auth.rate_limits.onboard_per_minute', 3))
                ->by('social-onboard|'.$request->ip().'|'.$request->route('provider'))
        );

        RateLimiter::for('api-social-confirm', fn (Request $request): Limit =>
            Limit::perMinute((int) config('tradex_social_auth.rate_limits.confirm_per_minute', 5))
                ->by('social-confirm|'.($request->input('registration_id') ?: $request->ip()))
        );

        RateLimiter::for('api-social-link', fn (Request $request): Limit =>
            Limit::perMinute((int) config('tradex_social_auth.rate_limits.link_per_minute', 5))
                ->by('social-link|'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
        );

        $this->loadRoutesFrom(base_path('routes/api_social_auth.php'));
    }
}
