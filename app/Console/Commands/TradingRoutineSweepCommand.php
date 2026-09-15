<?php

namespace App\Console\Commands;

use App\Services\Trading\TradingRoutineSweepService;
use Illuminate\Console\Command;

class TradingRoutineSweepCommand extends Command
{
    protected $signature = 'tradex:trading-routine:sweep {--limit= : Maximum enabled customer preferences to inspect}';

    protected $description = 'Generate due daily trading routine plans and submit opted-in WhatsApp PDF deliveries.';

    public function handle(TradingRoutineSweepService $sweep): int
    {
        $limit = $this->option('limit');
        $result = $sweep->sweep(is_numeric($limit) ? (int) $limit : null);

        if (! $result['enabled']) {
            $this->components->info('TradeX trading routine automation is disabled.');
            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Trading routine sweep: %d plans processed, %d WhatsApp accepted, %d failed, %d skipped.',
            $result['processed'],
            $result['accepted'],
            $result['failed'],
            $result['skipped']
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
