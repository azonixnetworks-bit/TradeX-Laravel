<?php

namespace App\Services\Admin\Security;

use App\Models\AdminUser;
use Illuminate\Support\Facades\Schema;

class AdminReleaseReadinessService
{
    public function evaluate(
        bool $production = false
    ): array {
        $checks = [];

        $this->add(
            $checks,
            'Admin TOTP schema',
            Schema::hasColumns(
                'admin_users',
                [
                    'totp_secret',
                    'totp_setup_at',
                    'totp_confirmed_at',
                    'totp_last_used_step',
                ]
            ),
            'critical',
            'ADMIN-19 TOTP columns must exist.'
        );

        $idle =
            (int) config(
                'tradex_admin_hardening.session.idle_timeout_minutes',
                30
            );

        $absolute =
            (int) config(
                'tradex_admin_hardening.session.absolute_timeout_minutes',
                480
            );

        $this->add(
            $checks,
            'Admin idle timeout',
            $idle >= 5
                && $idle <= 30,
            'critical',
            'Expected 5-30 minutes; configured '
                .$idle
                .' minutes.'
        );

        $this->add(
            $checks,
            'Admin absolute timeout',
            $absolute >= $idle
                && $absolute <= 720,
            'critical',
            'Expected between idle timeout and 720 minutes; configured '
                .$absolute
                .' minutes.'
        );

        $csp =
            (string) config(
                'tradex_admin_hardening.headers.content_security_policy',
                ''
            );

        $cspSafe =
            (bool) config(
                'tradex_admin_hardening.headers.csp_enabled',
                true
            )
            && str_contains(
                $csp,
                "default-src 'self'"
            )
            && str_contains(
                $csp,
                "frame-ancestors 'none'"
            )
            && str_contains(
                $csp,
                "object-src 'none'"
            )
            && str_contains(
                $csp,
                "form-action 'self'"
            )
            && str_contains(
                $csp,
                "script-src 'self'"
            );

        $this->add(
            $checks,
            'Admin CSP',
            $cspSafe,
            'critical',
            'CSP must keep same-origin defaults/forms/scripts and block framing/objects.'
        );

        $this->add(
            $checks,
            'CSP inline-script compatibility exception removed',
            preg_match(
                "/script-src[^;]*'unsafe-inline'/i",
                $csp
            ) !== 1,
            'warning',
            'Frozen legacy admin views still use inline confirmation handlers. Move them into same-origin static JavaScript in a future UI-only hardening pass before removing this compatibility exception.'
        );

        $this->add(
            $checks,
            'Session HTTP-only cookie',
            (bool) config(
                'session.http_only',
                true
            ),
            'critical',
            'SESSION_HTTP_ONLY must remain true.'
        );

        $sameSite =
            strtolower(
                (string) config(
                    'session.same_site',
                    ''
                )
            );

        $this->add(
            $checks,
            'Session SameSite',
            in_array(
                $sameSite,
                [
                    'lax',
                    'strict',
                ],
                true
            ),
            'critical',
            'SESSION_SAME_SITE should be lax or strict.'
        );

        $this->add(
            $checks,
            'Session serialization',
            strtolower(
                (string) config(
                    'session.serialization',
                    'json'
                )
            ) === 'json',
            'critical',
            'Session serialization must remain json.'
        );

        $this->add(
            $checks,
            'Session driver',
            strtolower(
                (string) config(
                    'session.driver',
                    'database'
                )
            ) !== 'array',
            'critical',
            'Production-capable session storage is required.'
        );

        $this->add(
            $checks,
            'Application key',
            filled(
                config('app.key')
            ),
            'critical',
            'APP_KEY must be configured. Its value is never displayed.'
        );

        if ($production) {
            $this->productionChecks(
                $checks
            );
        }

        $failedCritical =
            collect(
                $checks
            )
                ->filter(
                    fn (array $check): bool =>
                        $check['severity']
                            === 'critical'
                        && ! $check['passed']
                )
                ->count();

        $failedWarnings =
            collect(
                $checks
            )
                ->filter(
                    fn (array $check): bool =>
                        $check['severity']
                            === 'warning'
                        && ! $check['passed']
                )
                ->count();

        return [
            'mode' =>
                $production
                    ? 'PRODUCTION'
                    : 'BASELINE',

            'ready' =>
                $failedCritical === 0,

            'failed_critical' =>
                $failedCritical,

            'failed_warnings' =>
                $failedWarnings,

            'checks' =>
                $checks,
        ];
    }

    private function productionChecks(
        array &$checks
    ): void {
        $this->add(
            $checks,
            'APP_ENV production',
            app()->environment(
                'production'
            ),
            'critical',
            'Production deployment must use APP_ENV=production.'
        );

        $this->add(
            $checks,
            'APP_DEBUG disabled',
            ! (bool) config(
                'app.debug',
                false
            ),
            'critical',
            'APP_DEBUG must be false in production.'
        );

        $appUrl =
            strtolower(
                trim(
                    (string) config(
                        'app.url',
                        ''
                    )
                )
            );

        $this->add(
            $checks,
            'HTTPS application URL',
            str_starts_with(
                $appUrl,
                'https://'
            ),
            'critical',
            'APP_URL must use https:// in production.'
        );

        $this->add(
            $checks,
            'Secure session cookie',
            (bool) config(
                'session.secure',
                false
            ),
            'critical',
            'SESSION_SECURE_COOKIE must be true in production.'
        );

        $this->add(
            $checks,
            'Encrypted session storage',
            (bool) config(
                'session.encrypt',
                false
            ),
            'critical',
            'SESSION_ENCRYPT must be true in production.'
        );

        $allowedHosts =
            array_values(
                array_filter(
                    array_map(
                        static fn (
                            mixed $host
                        ): string =>
                            strtolower(
                                trim(
                                    (string) $host
                                )
                            ),
                        (array) config(
                            'tradex_admin_hardening.transport.allowed_hosts',
                            []
                        )
                    )
                )
            );

        $hostsValid =
            $allowedHosts !== []
            && collect(
                $allowedHosts
            )->every(
                fn (string $host): bool =>
                    preg_match(
                        '/^[a-z0-9.-]+$/',
                        $host
                    ) === 1
                    && ! str_contains(
                        $host,
                        '..'
                    )
            );

        $this->add(
            $checks,
            'Admin allowed hosts',
            $hostsValid,
            'critical',
            'TRADEX_ADMIN_ALLOWED_HOSTS must explicitly list exact production hostnames without schemes, ports or wildcards.'
        );

        $appHost =
            strtolower(
                (string) parse_url(
                    $appUrl,
                    PHP_URL_HOST
                )
            );

        $this->add(
            $checks,
            'APP_URL host allowlisted',
            $hostsValid
                && $appHost !== ''
                && in_array(
                    $appHost,
                    $allowedHosts,
                    true
                ),
            'critical',
            'The APP_URL hostname must be present in TRADEX_ADMIN_ALLOWED_HOSTS.'
        );

        $this->add(
            $checks,
            'Admin HTTPS enforcement',
            (bool) config(
                'tradex_admin_hardening.transport.require_https',
                false
            ),
            'critical',
            'TRADEX_ADMIN_REQUIRE_HTTPS must be true.'
        );

        $hstsEnabled =
            (bool) config(
                'tradex_admin_hardening.transport.hsts_enabled',
                false
            );

        $this->add(
            $checks,
            'Admin HSTS',
            $hstsEnabled,
            'critical',
            'TRADEX_ADMIN_HSTS_ENABLED must be true after HTTPS is verified.'
        );

        $hstsMaxAge =
            (int) config(
                'tradex_admin_hardening.transport.hsts_max_age_seconds',
                0
            );

        $this->add(
            $checks,
            'Admin HSTS max-age',
            $hstsEnabled
                && $hstsMaxAge >= 15552000,
            'critical',
            'Production HSTS max-age must be at least 15552000 seconds (180 days).'
        );

        $this->add(
            $checks,
            'Admin TOTP enforcement',
            (bool) config(
                'tradex_admin_hardening.totp.required',
                false
            ),
            'critical',
            'TRADEX_ADMIN_TOTP_REQUIRED must be true.'
        );

        if (
            Schema::hasColumns(
                'admin_users',
                [
                    'is_active',
                    'totp_secret',
                    'totp_confirmed_at',
                ]
            )
        ) {
            $activeCount =
                AdminUser::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->count();

            $unenrolledCount =
                AdminUser::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->where(
                        function (
                            $query
                        ): void {
                            $query
                                ->whereNull(
                                    'totp_confirmed_at'
                                )
                                ->orWhereNull(
                                    'totp_secret'
                                );
                        }
                    )
                    ->count();

            $this->add(
                $checks,
                'Active admin TOTP enrollment',
                $activeCount > 0
                    && $unenrolledCount === 0,
                'critical',
                'Active admins: '
                    .$activeCount
                    .'; without confirmed TOTP: '
                    .$unenrolledCount
                    .'.'
            );

            $invalidSecretCount = 0;

            if (
                $activeCount > 0
                && $unenrolledCount === 0
            ) {
                AdminUser::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->orderBy('id')
                    ->chunkById(
                        100,
                        function (
                            $admins
                        ) use (
                            &$invalidSecretCount
                        ): void {
                            foreach (
                                $admins
                                as $admin
                            ) {
                                try {
                                    $secret =
                                        (string) $admin
                                            ->totp_secret;

                                    if (
                                        preg_match(
                                            '/^[A-Z2-7]{16,}$/',
                                            $secret
                                        ) !== 1
                                    ) {
                                        $invalidSecretCount++;
                                    }
                                } catch (
                                    \Throwable
                                ) {
                                    $invalidSecretCount++;
                                }
                            }
                        }
                    );
            }

            $this->add(
                $checks,
                'Active admin TOTP secret integrity',
                $activeCount > 0
                    && $unenrolledCount === 0
                    && $invalidSecretCount === 0,
                'critical',
                'Unreadable or invalid active-admin TOTP secrets: '
                    .$invalidSecretCount
                    .'.'
            );
        } else {
            $this->add(
                $checks,
                'Active admin TOTP enrollment',
                false,
                'critical',
                'TOTP schema is unavailable.'
            );
        }

        $cacheDefault =
            strtolower(
                (string) config(
                    'cache.default',
                    ''
                )
            );

        $this->add(
            $checks,
            'Production shared cache backend',
            in_array(
                $cacheDefault,
                [
                    'redis',
                    'database',
                    'memcached',
                    'dynamodb',
                ],
                true
            ),
            'warning',
            'Use Redis, database, Memcached or DynamoDB-style shared cache for rate limiting in scaled production; local file/array stores are not shared across app nodes.'
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
            'name' =>
                $name,

            'passed' =>
                $passed,

            'severity' =>
                $severity,

            'detail' =>
                $detail,
        ];
    }
}
