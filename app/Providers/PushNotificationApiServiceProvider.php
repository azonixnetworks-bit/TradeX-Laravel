<?php

namespace App\Providers;

use App\Console\Commands\PushDeliverySweepCommand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PushNotificationApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PushDeliverySweepCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        RateLimiter::for(
            'api-push-device-read',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config(
                    'tradex_push.rate_limits.device_reads_per_minute',
                    60
                )
            )->by('push-read|'.(string) (
                $request->user()?->getAuthIdentifier()
                ?? $request->ip()
            ))
        );

        RateLimiter::for(
            'api-push-device-mutation',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config(
                    'tradex_push.rate_limits.device_mutations_per_minute',
                    20
                )
            )->by('push-mutation|'.(string) (
                $request->user()?->getAuthIdentifier()
                ?? $request->ip()
            ))
        );

        $this->loadRoutesFrom(base_path('routes/api_push.php'));

        $this->app->afterResolving(
            Schedule::class,
            function (Schedule $schedule): void {
                if (
                    (bool) config('tradex_push.enabled', false)
                    && (bool) config(
                        'tradex_push.sweep.schedule_every_minute',
                        true
                    )
                ) {
                    $schedule
                        ->command('tradex:push:sweep')
                        ->everyMinute()
                        ->withoutOverlapping();
                }
            }
        );
    }
}
