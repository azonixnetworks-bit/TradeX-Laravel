<?php

namespace App\Console\Commands;

use App\Services\TradeJournal\TradeJournalRetentionService;
use Illuminate\Console\Command;

class PruneTradeJournalCommand extends Command
{
    protected $signature = 'tradex:trade-journal:prune';

    protected $description = 'Permanently delete Trade Journal data older than the retention period';

    public function handle(TradeJournalRetentionService $retention): int
    {
        $deleted = $retention->pruneExpired();

        $this->info(sprintf(
            'Deleted %d expired Trade Journal record(s); retention is %d days.',
            $deleted,
            $retention->retentionDays()
        ));

        return self::SUCCESS;
    }
}
