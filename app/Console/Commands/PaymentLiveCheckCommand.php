<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentLiveReadinessService;
use Illuminate\Console\Command;
use Throwable;

class PaymentLiveCheckCommand extends Command
{
    protected $signature = 'tradex:payment-live-check {--production : Require live production payment configuration}';

    protected $description = 'Validate Stripe and NOWPayments sandbox/live readiness without exposing secrets';

    public function handle(PaymentLiveReadinessService $service): int
    {
        try {
            $result = $service->evaluate((bool) $this->option('production'));
        } catch (Throwable) {
            $this->error('Payment integration readiness check failed safely. Review application configuration and logs.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('TradeX Payment Live Check — '.$result['mode']);

        $this->table(
            ['Status', 'Severity', 'Check', 'Detail'],
            collect($result['checks'])
                ->map(fn (array $check): array => [
                    $check['passed'] ? 'PASS' : 'FAIL',
                    strtoupper($check['severity']),
                    $check['name'],
                    $check['detail'],
                ])
                ->all()
        );

        if (! $result['ready']) {
            $this->error(
                'Payment integration readiness FAILED: '
                .$result['failed_critical']
                .' critical gate(s) failed.'
            );

            return self::FAILURE;
        }

        if ($result['failed_warnings'] > 0) {
            $this->warn(
                'Payment integration readiness PASSED with '
                .$result['failed_warnings']
                .' warning(s).'
            );
        } else {
            $this->info('Payment integration readiness PASSED.');
        }

        return self::SUCCESS;
    }
}
