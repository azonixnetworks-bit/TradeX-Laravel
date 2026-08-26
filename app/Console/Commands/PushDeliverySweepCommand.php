<?php

namespace App\Console\Commands;

use App\Services\Push\PushFanoutService;
use Illuminate\Console\Command;

class PushDeliverySweepCommand extends Command
{
    protected $signature = 'tradex:push:sweep {--limit= : Maximum deliveries to prepare in this sweep}';

    protected $description =
        'Prepare and queue due TradeX push notification deliveries.';

    public function handle(PushFanoutService $service): int
    {
        $limit = $this->option('limit');

        $result = $service->sweep(
            is_numeric($limit) ? (int) $limit : null
        );

        if (! $result['enabled']) {
            $this->components->info('TradeX push delivery is disabled.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Push sweep complete: %d delivery rows created, %d jobs queued.',
            $result['created'],
            $result['queued']
        ));

        return self::SUCCESS;
    }
}
