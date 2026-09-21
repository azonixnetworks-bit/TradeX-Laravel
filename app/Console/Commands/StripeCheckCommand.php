<?php

namespace App\Console\Commands;

use App\Services\Payments\StripeConfigurationSafety;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class StripeCheckCommand extends Command
{
    protected $signature = 'tradex:stripe-check {--probe : Make a read-only authenticated Stripe API request}';

    protected $description = 'Check Stripe configuration without exposing credentials or creating a payment';

    public function handle(StripeConfigurationSafety $configuration): int
    {
        $checks = $configuration->checks();

        $this->table(['Check', 'Result'], collect($checks)->map(
            fn (bool $valid, string $name) => [$name, $valid ? 'PASS' : 'MISSING / INVALID']
        )->values()->all());

        if (in_array(false, $checks, true)) {
            $this->error('Stripe is not ready. Follow docs/STRIPE-LIVE-SETUP.md, then run php artisan config:cache.');

            return self::FAILURE;
        }

        if (! $this->option('probe')) {
            $this->info('Local configuration checks passed. No external API request was made.');

            return self::SUCCESS;
        }

        try {
            $response = Http::withToken((string) config('tradex_integrations.stripe.secret_key'))
                ->acceptJson()
                ->timeout((int) config('tradex_integrations.stripe.timeout_seconds', 15))
                ->get('https://api.stripe.com/v1/balance');
        } catch (ConnectionException) {
            $this->error('Stripe API could not be reached.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Stripe rejected the read-only probe (HTTP '.$response->status().').');

            return self::FAILURE;
        }

        $this->info('Stripe API probe passed. No balance values were printed and no payment was created.');

        return self::SUCCESS;
    }
}
