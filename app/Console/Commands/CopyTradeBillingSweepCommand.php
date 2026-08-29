<?php

namespace App\Console\Commands;

use App\Models\CopyTradeBrokerAccount;
use App\Services\CopyTrading\Billing\CopyTradeBillingEngine;
use Illuminate\Console\Command;

class CopyTradeBillingSweepCommand extends Command
{
    protected $signature =
        'tradex:copy-trade-billing:sweep
        {--account= : Optional broker account UUID}';

    protected $description =
        'Run the frozen Copy Trade billing renewal and grace sweep.';

    public function handle(
        CopyTradeBillingEngine $engine
    ): int {
        $uuid = trim(
            (string) $this->option(
                'account'
            )
        );

        if ($uuid !== '') {
            $account =
                CopyTradeBrokerAccount::query()
                    ->where(
                        'uuid',
                        $uuid
                    )
                    ->firstOrFail();

            $result =
                $engine->sweepAccount(
                    $account
                );

            $this->line(
                (string) json_encode(
                    $result,
                    JSON_PRETTY_PRINT
                )
            );

            return self::SUCCESS;
        }

        $summary = $engine->sweepAll();

        $this->line(
            (string) json_encode(
                $summary,
                JSON_PRETTY_PRINT
            )
        );

        return self::SUCCESS;
    }
}
