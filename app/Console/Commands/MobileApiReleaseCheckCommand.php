<?php

namespace App\Console\Commands;

use App\Services\Api\MobileApiReleaseReadinessService;
use Illuminate\Console\Command;

class MobileApiReleaseCheckCommand extends Command
{
    protected $signature =
        'tradex:mobile-api-release-check
        {--production : Apply production deployment gates}';

    protected $description =
        'Validate TradeX mobile API final hardening and release-readiness gates.';

    public function handle(
        MobileApiReleaseReadinessService $service
    ): int {
        try {
            $result = $service->evaluate(
                (bool) $this->option('production')
            );
        } catch (\Throwable) {
            /*
             * Fail closed without printing connection details, paths,
             * credentials or other deployment-sensitive exception text.
             */
            $this->error(
                'Mobile API release readiness could not complete because an application or database readiness dependency failed.'
            );

            return self::FAILURE;
        }

        $this->info(
            'TradeX Mobile API Release Check — '.$result['mode']
        );

        $rows = [];

        foreach ($result['checks'] as $check) {
            $rows[] = [
                $check['passed'] ? 'PASS' : 'FAIL',
                strtoupper($check['severity']),
                $check['name'],
                $check['detail'],
            ];
        }

        $this->table(
            ['Status', 'Severity', 'Check', 'Detail'],
            $rows
        );

        if (! $result['ready']) {
            $this->error(
                'Mobile API release readiness FAILED: '
                .$result['failed_critical']
                .' critical gate(s) failed.'
            );

            return self::FAILURE;
        }

        if ($result['failed_warnings'] > 0) {
            $this->warn(
                'Critical gates passed with '
                .$result['failed_warnings']
                .' warning(s).'
            );
        }

        $this->info('Mobile API release readiness PASSED.');

        return self::SUCCESS;
    }
}
