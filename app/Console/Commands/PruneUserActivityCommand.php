<?php

namespace App\Console\Commands;

use App\Models\UserActivityEvent;
use Illuminate\Console\Command;

class PruneUserActivityCommand extends Command
{
    protected $signature = 'tradex:user-activity:prune';

    protected $description = 'Delete user activity events older than 45 days';

    public function handle(): int
    {
        $deleted = UserActivityEvent::query()
            ->where('occurred_at', '<', now()->subDays(45))
            ->delete();

        $this->info("Pruned {$deleted} user activity event(s).");

        return self::SUCCESS;
    }
}
