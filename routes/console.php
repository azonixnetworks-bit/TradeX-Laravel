<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tradex:finance:daily-sales-report')
    ->dailyAt(
        (string) config(
            'tradex_finance.daily_report.time',
            '00:10'
        )
    )
    ->timezone(
        (string) config(
            'tradex_finance.timezone',
            'Asia/Colombo'
        )
    )
    ->withoutOverlapping(60)
    ->when(
        fn (): bool => (bool) config(
            'tradex_finance.daily_report.enabled',
            false
        )
    );

Schedule::command('tradex:copy-trade-billing:sweep')
    ->hourly()
    ->withoutOverlapping(60)
    ->when(
        fn (): bool => (bool) config(
            'tradex_copy_trading.billing.sweep_enabled',
            false
        )
    );
