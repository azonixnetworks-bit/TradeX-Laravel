<?php

namespace App\Console\Commands;

use App\Models\UserActivityEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneUserActivityCommand extends Command
{
    protected $signature = 'tradex:user-activity:prune';

    protected $description = 'Delete user activity events older than 45 days';

    public function handle(): int
    {
        // A release may register scheduled jobs before its migrations run.
        if (! Schema::hasTable('user_activity_events')) {
            $this->warn('Activity table not yet migrated; skipping cleanup.');
            return self::SUCCESS;
        }

        $deleted = UserActivityEvent::query()
            ->where('occurred_at', '<', now()->subDays(45))
            ->delete();

        $this->info("Pruned {$deleted} user activity event(s).");

        return self::SUCCESS;
    }
}
