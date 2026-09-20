<?php

namespace App\Console\Commands;

use App\Services\Payments\NowPaymentsConfigurationSafety;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class NowPaymentsCheckCommand extends Command
{
    protected $signature = 'tradex:nowpayments-check {--probe : Call the read-only NOWPayments API status endpoint}';

    protected $description = 'Check NOWPayments configuration without exposing credentials or creating a payment';

    public function handle(NowPaymentsConfigurationSafety $configuration): int
    {
        $checks = $configuration->checks();

        $this->table(['Check', 'Result'], collect($checks)->map(
            fn (bool $valid, string $name) => [$name, $valid ? 'PASS' : 'MISSING / INVALID']
        )->values()->all());

        if (in_array(false, $checks, true)) {
            $this->error('NOWPayments is not ready. Follow docs/NOWPAYMENTS-LIVE-SETUP.md, then run php artisan config:cache.');

            return self::FAILURE;
        }

        if (! $this->option('probe')) {
            $this->info('Local configuration checks passed. No external API request was made.');

            return self::SUCCESS;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'x-api-key' => (string) config('tradex_integrations.nowpayments.api_key'),
                ])
                ->timeout((int) config('tradex_integrations.nowpayments.timeout_seconds', 15))
                ->get($configuration->selectedBaseUrl().'/v1/status');
        } catch (ConnectionException) {
            $this->error('NOWPayments status endpoint could not be reached.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('NOWPayments rejected the status request (HTTP '.$response->status().').');

            return self::FAILURE;
        }

        $this->info('NOWPayments API status probe passed. No payment or invoice was created.');

        return self::SUCCESS;
    }
}
