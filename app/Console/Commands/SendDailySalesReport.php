<?php

namespace App\Console\Commands;

use App\Mail\DailySalesReportMail;
use App\Services\Admin\Finance\AdminFinanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailySalesReport extends Command
{
    protected $signature =
        'tradex:finance:daily-sales-report
        {--date= : Local business date in YYYY-MM-DD}
        {--to= : Override report recipient email}';

    protected $description =
        'Send the TradeX owner daily finance report.';

    public function handle(
        AdminFinanceService $service
    ): int {
        $date = trim(
            (string) $this->option('date')
        );

        if ($date === '') {
            $date = $service->previousLocalDate();
        }

        if (
            ! preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $date
            )
        ) {
            $this->error(
                'The report date must use YYYY-MM-DD.'
            );

            return self::FAILURE;
        }

        $recipient = trim(
            (string) (
                $this->option('to')
                ?: config(
                    'tradex_finance.daily_report.owner_email',
                    ''
                )
            )
        );

        if (
            $recipient === ''
            || filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $this->error(
                'TRADEX_FINANCE_OWNER_REPORT_EMAIL is not configured with a valid email address.'
            );

            return self::FAILURE;
        }

        $report = $service->dailyReport(
            $date
        );

        Mail::to($recipient)->send(
            new DailySalesReportMail(
                $report,
                $date,
                (string) config(
                    'tradex_finance.timezone',
                    'Asia/Colombo'
                )
            )
        );

        $this->info(
            'TradeX daily finance report sent for '.$date.'.'
        );

        return self::SUCCESS;
    }
}
