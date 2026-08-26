<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RuntimeTranslationApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // API-18 uses constructor injection only.
    }

    public function boot(): void
    {
        /*
         * Frozen boundary: ADMIN-18 owns tradex_languages.runtime_translation_api
         * and intentionally keeps its original NOT_WIRED marker. API-18 exposes
         * its runtime state from tradex_translation_api instead of mutating the
         * frozen ADMIN-18 configuration namespace.
         */
        RateLimiter::for(
            'api-runtime-translations',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config(
                    'tradex_translation_api.rate_limits.read_per_minute',
                    120
                )
            )->by('runtime-translations|'.$request->ip())
        );

        $this->loadRoutesFrom(
            base_path('routes/api_translations.php')
        );
    }
}
