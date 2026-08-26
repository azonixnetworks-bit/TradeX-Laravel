<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Schema;

class MobileApiReleaseReadinessService
{
    public function evaluate(
        bool $production = false
    ): array {
        $checks = [];

        $this->add(
            $checks,
            'API-23 release contract',
            config('tradex_mobile_api_hardening.release_contract')
                === 'API-23-FINAL',
            'critical',
            'The final mobile API hardening contract must be active.'
        );

        $this->add(
            $checks,
            'Application key',
            filled(config('app.key')),
            'critical',
            'APP_KEY must be configured. Its value is never displayed.'
        );

        $accessMinutes = (int) config(
            'tradex_auth.access_token_minutes',
            0
        );

        $this->add(
            $checks,
            'Access-token lifetime',
            $accessMinutes >= 5
                && $accessMinutes <= 60,
            'critical',
            'Access tokens must expire between 5 and 60 minutes; configured '
                .$accessMinutes
                .' minutes.'
        );

        $refreshDays = (int) config(
            'tradex_auth.refresh_token_days',
            0
        );

        $this->add(
            $checks,
            'Refresh-token lifetime',
            $refreshDays >= 1
                && $refreshDays <= 90,
            'critical',
            'Refresh tokens must expire between 1 and 90 days; configured '
                .$refreshDays
                .' days.'
        );

        $totpPeriod = (int) config(
            'tradex_auth.totp.period_seconds',
            0
        );
        $totpDigits = (int) config(
            'tradex_auth.totp.digits',
            0
        );
        $totpWindow = (int) config(
            'tradex_auth.totp.window',
            -1
        );

        $this->add(
            $checks,
            'TOTP contract',
            $totpPeriod === 30
                && $totpDigits === 6
                && $totpWindow >= 0
                && $totpWindow <= 1,
            'critical',
            'TOTP must remain 30-second, 6-digit, with a maximum one-step window.'
        );

        $this->add(
            $checks,
            'Trusted-device schema',
            Schema::hasColumns(
                'user_devices',
                [
                    'user_id',
                    'device_slot',
                    'platform',
                    'installation_id_hash',
                    'is_trusted',
                    'is_active',
                    'revoked_at',
                ]
            ),
            'critical',
            'Trusted-device binding columns must exist.'
        );

        $this->add(
            $checks,
            'Access-token device binding schema',
            Schema::hasColumns(
                'personal_access_tokens',
                [
                    'tokenable_id',
                    'token',
                    'user_device_id',
                    'session_uuid',
                    'expires_at',
                ]
            ),
            'critical',
            'Sanctum access tokens must retain device/session binding and expiry.'
        );

        $this->add(
            $checks,
            'Refresh-token rotation schema',
            Schema::hasColumns(
                'user_refresh_tokens',
                [
                    'user_id',
                    'user_device_id',
                    'token_hash',
                    'family_uuid',
                    'expires_at',
                    'revoked_at',
                ]
            ),
            'critical',
            'Hashed, device-bound refresh-token rotation fields must exist.'
        );

        if ($production) {
            $this->productionChecks($checks);
        }

        $failedCritical = collect($checks)
            ->filter(
                fn (array $check): bool =>
                    $check['severity'] === 'critical'
                    && ! $check['passed']
            )
            ->count();

        $failedWarnings = collect($checks)
            ->filter(
                fn (array $check): bool =>
                    $check['severity'] === 'warning'
                    && ! $check['passed']
            )
            ->count();

        return [
            'mode' => $production
                ? 'PRODUCTION'
                : 'BASELINE',
            'ready' => $failedCritical === 0,
            'failed_critical' => $failedCritical,
            'failed_warnings' => $failedWarnings,
            'checks' => $checks,
        ];
    }

    private function productionChecks(
        array &$checks
    ): void {
        $this->add(
            $checks,
            'APP_ENV production',
            app()->environment('production'),
            'critical',
            'Production deployment must use APP_ENV=production.'
        );

        $this->add(
            $checks,
            'APP_DEBUG disabled',
            ! (bool) config('app.debug', false),
            'critical',
            'APP_DEBUG must be false in production.'
        );

        $appUrl = strtolower(
            trim((string) config('app.url', ''))
        );

        $this->add(
            $checks,
            'HTTPS application URL',
            str_starts_with($appUrl, 'https://'),
            'critical',
            'APP_URL must use https:// in production.'
        );

        $allowedHosts = array_values(
            array_filter(
                array_map(
                    static fn (mixed $host): string => strtolower(
                        trim((string) $host)
                    ),
                    (array) config(
                        'tradex_mobile_api_hardening.transport.allowed_hosts',
                        []
                    )
                )
            )
        );

        $hostsValid = $allowedHosts !== []
            && collect($allowedHosts)->every(
                fn (string $host): bool =>
                    preg_match('/^[a-z0-9.-]+$/', $host) === 1
                    && ! str_contains($host, '..')
                    && ! str_starts_with($host, '.')
                    && ! str_ends_with($host, '.')
            );

        $this->add(
            $checks,
            'API allowed hosts',
            $hostsValid,
            'critical',
            'TRADEX_API_ALLOWED_HOSTS must explicitly list exact production hostnames without schemes, ports or wildcards.'
        );

        $appHost = strtolower(
            (string) parse_url($appUrl, PHP_URL_HOST)
        );

        $this->add(
            $checks,
            'APP_URL host allowlisted',
            $hostsValid
                && $appHost !== ''
                && in_array($appHost, $allowedHosts, true),
            'critical',
            'The APP_URL hostname must be present in TRADEX_API_ALLOWED_HOSTS.'
        );

        $this->add(
            $checks,
            'API HTTPS enforcement',
            (bool) config(
                'tradex_mobile_api_hardening.transport.require_https',
                false
            ),
            'critical',
            'TRADEX_API_REQUIRE_HTTPS must be true.'
        );

        $hstsEnabled = (bool) config(
            'tradex_mobile_api_hardening.transport.hsts_enabled',
            false
        );

        $this->add(
            $checks,
            'API HSTS',
            $hstsEnabled,
            'critical',
            'TRADEX_API_HSTS_ENABLED must be true after HTTPS is verified.'
        );

        $hstsMaxAge = (int) config(
            'tradex_mobile_api_hardening.transport.hsts_max_age_seconds',
            0
        );

        $this->add(
            $checks,
            'API HSTS max-age',
            $hstsEnabled
                && $hstsMaxAge >= 15552000,
            'critical',
            'Production HSTS max-age must be at least 15552000 seconds (180 days).'
        );

        $database = strtolower(
            (string) config('database.default', '')
        );

        $this->add(
            $checks,
            'Production database backend',
            in_array(
                $database,
                ['mysql', 'mariadb', 'pgsql'],
                true
            ),
            'critical',
            'Production mobile API must use a server database backend, not SQLite.'
        );

        $cache = strtolower(
            (string) config('cache.default', '')
        );

        $this->add(
            $checks,
            'Shared cache backend',
            in_array(
                $cache,
                ['redis', 'database', 'memcached', 'dynamodb'],
                true
            ),
            'critical',
            'A shared cache backend is required for consistent rate limiting across app nodes.'
        );

        $queue = strtolower(
            (string) config('queue.default', '')
        );

        $this->add(
            $checks,
            'Asynchronous queue backend',
            in_array(
                $queue,
                ['database', 'redis', 'sqs', 'beanstalkd'],
                true
            ),
            'critical',
            'A production-capable asynchronous queue backend is required for push/video workloads.'
        );

        $mailer = strtolower(
            (string) config('mail.default', '')
        );

        $this->add(
            $checks,
            'Transactional mail transport',
            ! in_array(
                $mailer,
                ['', 'log', 'array'],
                true
            ),
            'critical',
            'A real transactional mail transport is required for OTP and account-recovery delivery.'
        );

        $pushEnabled = (bool) config(
            'tradex_push.enabled',
            false
        );

        if ($pushEnabled) {
            $fcmEnabled = (bool) config(
                'tradex_push.fcm.enabled',
                false
            );
            $apnsEnabled = (bool) config(
                'tradex_push.apns.enabled',
                false
            );

            $this->add(
                $checks,
                'Push provider enabled',
                $fcmEnabled || $apnsEnabled,
                'critical',
                'When push is globally enabled, at least one FCM/APNs provider must be enabled.'
            );

            if ($fcmEnabled) {
                $this->add(
                    $checks,
                    'FCM runtime configuration',
                    filled(config('tradex_push.fcm.project_id'))
                        && filled(config('tradex_push.fcm.credentials_path')),
                    'critical',
                    'Enabled FCM requires project and credential configuration. Values are never displayed.'
                );
            }

            if ($apnsEnabled) {
                $this->add(
                    $checks,
                    'APNs runtime configuration',
                    filled(config('tradex_push.apns.team_id'))
                        && filled(config('tradex_push.apns.key_id'))
                        && filled(config('tradex_push.apns.bundle_id'))
                        && filled(config('tradex_push.apns.private_key_path')),
                    'critical',
                    'Enabled APNs requires team, key, bundle and private-key configuration. Values are never displayed.'
                );
            }
        }

        $logLevel = strtolower(
            (string) config('logging.level', env('LOG_LEVEL', 'debug'))
        );

        $this->add(
            $checks,
            'Production log level',
            $logLevel !== 'debug',
            'warning',
            'Avoid DEBUG logging in production unless temporarily required for an incident.'
        );
    }

    private function add(
        array &$checks,
        string $name,
        bool $passed,
        string $severity,
        string $detail
    ): void {
        $checks[] = [
            'name' => $name,
            'passed' => $passed,
            'severity' => $severity,
            'detail' => $detail,
        ];
    }
}
