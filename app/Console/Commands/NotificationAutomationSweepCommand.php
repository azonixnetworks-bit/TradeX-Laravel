<?php

namespace App\Console\Commands;

use App\Services\Notifications\Automation\NotificationAutomationProcessor;
use Illuminate\Console\Command;

class NotificationAutomationSweepCommand extends Command
{
    protected $signature = 'tradex:notifications:sweep {--limit= : Maximum automation events to process}';

    protected $description =
        'Process pending TradeX automatic notification events.';

    public function handle(NotificationAutomationProcessor $processor): int
    {
        $limit = $this->option('limit');

        $result = $processor->sweep(
            is_numeric($limit) ? (int) $limit : null
        );

        if (! $result['enabled']) {
            $this->components->info('TradeX notification automation is disabled.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Notification automation sweep: %d processed, %d failed, %d skipped.',
            $result['processed'],
            $result['failed'],
            $result['skipped']
        ));

        return $result['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
