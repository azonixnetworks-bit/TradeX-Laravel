<?php

namespace App\Console\Commands;

use App\Services\Admin\Security\AdminReleaseReadinessService;
use Illuminate\Console\Command;

class AdminReleaseCheckCommand extends Command
{
    protected $signature =
        'tradex:admin-release-check
        {--production : Apply production deployment gates}';

    protected $description =
        'Validate TradeX admin hardening and release-readiness gates.';

    public function handle(
        AdminReleaseReadinessService $service
    ): int {
        try {
            $result =
                $service->evaluate(
                    (bool) $this->option(
                        'production'
                    )
                );
        } catch (\Throwable) {
            /*
             * Fail closed without printing exception text that could contain
             * connection details, paths or other deployment-sensitive data.
             */
            $this->error(
                'Admin release readiness could not complete because an application or database readiness dependency failed.'
            );

            return self::FAILURE;
        }

        $this->info(
            'TradeX Admin Release Check — '
            .$result['mode']
        );

        $rows = [];

        foreach (
            $result['checks']
            as $check
        ) {
            $rows[] = [
                $check['passed']
                    ? 'PASS'
                    : 'FAIL',

                strtoupper(
                    $check['severity']
                ),

                $check['name'],

                $check['detail'],
            ];
        }

        $this->table(
            [
                'Status',
                'Severity',
                'Check',
                'Detail',
            ],
            $rows
        );

        if (
            ! $result['ready']
        ) {
            $this->error(
                'Admin release readiness FAILED: '
                .$result['failed_critical']
                .' critical gate(s) failed.'
            );

            return self::FAILURE;
        }

        if (
            $result['failed_warnings'] > 0
        ) {
            $this->warn(
                'Critical gates passed with '
                .$result['failed_warnings']
                .' warning(s).'
            );
        }

        $this->info(
            'Admin release readiness PASSED.'
        );

        return self::SUCCESS;
    }
}
