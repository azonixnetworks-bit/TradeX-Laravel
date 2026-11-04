<?php

namespace App\Console\Commands;

use App\Services\Audit\ActivityAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ActivityReadinessCheckCommand extends Command
{
    protected $signature = 'tradex:user-activity:check
        {--allow-disabled : Pass when schema/routes are ready but tracking is disabled}';

    protected $description = 'Check activity tracking deployment readiness without reading customer data';

    public function handle(ActivityAvailability $availability): int
    {
        $schemaReady = $availability->schemaReady();
        $routesReady = Route::has('api.v1.activity.config')
            && Route::has('api.v1.activity.events.ingest');
        $enabled = (bool) config('tradex_activity.enabled', false);

        $this->line('Activity table: '.($schemaReady ? 'READY' : 'MISSING'));
        $this->line('Activity API routes: '.($routesReady ? 'READY' : 'MISSING'));
        $this->line('TRADEX_ACTIVITY_ENABLED: '.($enabled ? 'true' : 'false'));

        if (! $schemaReady || ! $routesReady) {
            $this->error('Activity tracking is not ready. Apply migrations and verify routes.');
            return self::FAILURE;
        }

        if (! $enabled && ! $this->option('allow-disabled')) {
            $this->warn('Activity tracking is intentionally disabled.');
            return self::FAILURE;
        }

        $this->info('Activity tracking deployment prerequisites verified.');
        return self::SUCCESS;
    }
}
