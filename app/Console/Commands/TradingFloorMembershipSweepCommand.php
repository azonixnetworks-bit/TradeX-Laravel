<?php

namespace App\Console\Commands;

use App\Services\Admin\TradingFloor\TradingFloorMembershipService;
use Illuminate\Console\Command;

class TradingFloorMembershipSweepCommand extends Command
{
    protected $signature = 'tradex:trading-floor:memberships-sweep';

    protected $description = 'Expire ended Trading Floor memberships and create pending auto-renewal records where eligible.';

    public function handle(TradingFloorMembershipService $service): int
    {
        if (! (bool) config('trading_floor.memberships.sweep_enabled', true)) {
            $this->components->info('Trading Floor membership sweep is disabled.');
            return self::SUCCESS;
        }

        $summary = $service->sweepExpiries();
        $this->components->info(sprintf(
            'Membership sweep: %d expired, %d pending auto-renewals created, %d auto-renewals skipped.',
            $summary['expired'],
            $summary['auto_renewals_created'],
            $summary['auto_renewals_skipped']
        ));

        return self::SUCCESS;
    }
}
