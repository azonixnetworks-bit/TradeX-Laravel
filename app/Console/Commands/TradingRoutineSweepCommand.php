<?php

namespace App\Console\Commands;

use App\Services\Trading\TradingRoutineSweepService;
use Illuminate\Console\Command;

class TradingRoutineSweepCommand extends Command
{
    protected $signature = 'tradex:trading-routine:sweep {--batch= : Database chunk size for enabled customer preferences}';

    protected $description = 'Generate due daily trading routine plans and submit opted-in WhatsApp PDF deliveries.';

    public function handle(TradingRoutineSweepService $sweep): int
    {
        $batch = $this->option('batch');
        $result = $sweep->sweep(is_numeric($batch) ? (int) $batch : null);

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
