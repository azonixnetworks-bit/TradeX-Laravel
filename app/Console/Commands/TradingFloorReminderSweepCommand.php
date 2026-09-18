<?php

namespace App\Console\Commands;

use App\Services\Admin\TradingFloor\TradingFloorReminderService;
use Illuminate\Console\Command;

class TradingFloorReminderSweepCommand extends Command
{
    protected $signature = 'tradex:trading-floor:reminders';

    protected $description = 'Generate Trading Floor commission, payment and membership renewal reminder queue items.';

    public function handle(TradingFloorReminderService $service): int
    {
        if (! (bool) config('trading_floor.reminders.automation_enabled', false)) {
            $this->components->info('Trading Floor reminder automation is disabled.');

            return self::SUCCESS;
        }

        $summary = $service->generate();

        $this->components->info(sprintf(
            'Trading Floor reminders: %d created, %d already queued, %d skipped.',
            $summary['created'],
            $summary['existing'],
            $summary['skipped']
        ));

        return self::SUCCESS;
    }
}
