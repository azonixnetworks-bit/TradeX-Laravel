<?php

namespace App\Services\Admin\Security;

use App\Models\AuditLog;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminSecurityService
{
    public const SESSION_ACTIVE = 'ACTIVE';

    public const SESSION_EXPIRED = 'EXPIRED';

    public const SESSION_REVOKED = 'REVOKED';

    public function events(
        array $filters
    ): array {
        $query =
            $this->securityEventsQuery();

        $this->applyEventFilters(
            $query,
            $filters
        );

        return [
            'events' =>
                $query
                    ->orderByDesc(
                        'created_at'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->paginate(
                        $this->perPage(
                            $filters
                        )
                    )
                    ->withQueryString(),

            'stats' =>
                $this->securityStats(),

            'actions' =>
                $this->securityEventsQuery()
                    ->select('action')
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action')
                    ->filter(
                        fn ($value): bool =>
                            is_string($value)
                            && $value !== ''
                    )
                    ->values()
                    ->all(),

            'modules' =>
                $this->securityEventsQuery()
                    ->whereNotNull(
                        'module'
                    )
                    ->select('module')
                    ->distinct()
                    ->orderBy('module')
                    ->pluck('module')
                    ->filter(
                        fn ($value): bool =>
                            is_string($value)
                            && $value !== ''
                    )
                    ->values()
                    ->all(),

            'actor_types' =>
                $this->securityEventsQuery()
                    ->select(
                        'actor_type'
                    )
                    ->distinct()
                    ->orderBy(
                        'actor_type'
                    )
                    ->pluck(
                        'actor_type'
                    )
                    ->filter(
                        fn ($value): bool =>
                            is_string($value)
                            && $value !== ''
                    )
                    ->values()
                    ->all(),
        ];
    }

    public function sessions(
        array $filters
    ): array {
        $query =
            $this->sessionQuery();

        $this->applySessionFilters(
            $query,
            $filters
        );

        /** @var LengthAwarePaginator $sessions */
        $sessions = $query
            ->orderByDesc(
                'urt.created_at'
            )
            ->orderByDesc(
                'urt.id'
            )
            ->paginate(
                $this->perPage(
                    $filters
                )
            )
            ->withQueryString();

        $sessions->getCollection()
            ->transform(
                function (
                    object $row
                ): object {
                    $row->session_status =
                        $this->sessionStatus(
                            $row->revoked_at,
                            $row->expires_at
                        );

                    return $row;
                }
            );

        return [
            'sessions' =>
                $sessions,

            'stats' =>
                $this->securityStats(),

            'statuses' => [
                self::SESSION_ACTIVE,
                self::SESSION_EXPIRED,
                self::SESSION_REVOKED,
            ],

            'slots' => [
                UserDevice::SLOT_MOBILE,
                UserDevice::SLOT_DESKTOP,
            ],

            'platforms' => [
                UserDevice::PLATFORM_ANDROID,
                UserDevice::PLATFORM_IOS,
                UserDevice::PLATFORM_WINDOWS,
                UserDevice::PLATFORM_MACOS,
                UserDevice::PLATFORM_WEB,
            ],
        ];
    }

    public function contentProtection(): array
    {
        $privateDisk =
            (array) config(
                'filesystems.disks.private',
                []
            );

        $kycDiskName = trim(
            (string) config(
                'tradex_kyc.storage.disk',
                'private'
            )
        );

        $kycDisk =
            (array) config(
                'filesystems.disks.'
                .$kycDiskName,
                []
            );

        $sessionDriver =
            (string) config(
                'session.driver',
                ''
            );

        $sessionSecure =
            config(
                'session.secure'
            );

        $contentProtection =
            (array) config(
                'content-protection',
                []
            );

        $checks = [
            [
                'key' =>
                    'private_media_disk',

                'name' =>
                    'Protected private filesystem',

                'status' =>
                    $this->isPrivateDisk(
                        $privateDisk
                    )
                        ? 'PASS'
                        : 'WARN',

                'detail' =>
                    $this->isPrivateDisk(
                        $privateDisk
                    )
                        ? 'The TradeX private disk is not public and does not expose direct serving.'
                        : 'The TradeX private disk requires review before protected media is deployed.',
            ],

            [
                'key' =>
                    'kyc_private_storage',

                'name' =>
                    'KYC private storage',

                'status' =>
                    $kycDiskName !== ''
                    && strtolower(
                        $kycDiskName
                    ) !== 'public'
                    && $this->isPrivateDisk(
                        $kycDisk,
                        false
                    )
                        ? 'PASS'
                        : 'WARN',

                'detail' =>
                    'KYC disk: '
                    .($kycDiskName !== ''
                        ? $kycDiskName
                        : 'not configured')
                    .'.',
            ],

            [
                'key' =>
                    'session_http_only',

                'name' =>
                    'Admin session HttpOnly cookie',

                'status' =>
                    (bool) config(
                        'session.http_only',
                        true
                    )
                        ? 'PASS'
                        : 'WARN',

                'detail' =>
                    (bool) config(
                        'session.http_only',
                        true
                    )
                        ? 'Browser JavaScript cannot read the session cookie.'
                        : 'HttpOnly is disabled and should be enabled.',
            ],

            [
                'key' =>
                    'session_same_site',

                'name' =>
                    'Admin session SameSite policy',

                'status' =>
                    in_array(
                        strtolower(
                            (string) config(
                                'session.same_site',
                                ''
                            )
                        ),
                        [
                            'lax',
                            'strict',
                        ],
                        true
                    )
                        ? 'PASS'
                        : 'WARN',

                'detail' =>
                    'Effective SameSite: '
                    .(
                        (string) config(
                            'session.same_site',
                            'not configured'
                        )
                    )
                    .'.',
            ],

            [
                'key' =>
                    'session_secure_cookie',

                'name' =>
                    'HTTPS-only session cookie',

                'status' =>
                    $sessionSecure === true
                        ? 'PASS'
                        : 'WARN',

                'detail' =>
                    $sessionSecure === true
                        ? 'Secure cookie flag is explicitly enabled.'
                        : (
                            $sessionSecure === null
                                ? 'Secure cookie flag is environment-controlled but not explicitly enabled in the current runtime.'
                                : 'Secure cookie flag is disabled in the current runtime.'
                        ),
            ],

            [
                'key' =>
                    'session_serialization',

                'name' =>
                    'Safe session serialization',

                'status' =>
                    strtolower(
                        (string) config(
                            'session.serialization',
                            ''
                        )
                    ) === 'json'
                        ? 'PASS'
                        : 'WARN',

                'detail' =>
                    'Effective serializer: '
                    .(
                        (string) config(
                            'session.serialization',
                            'not configured'
                        )
                    )
                    .'.',
            ],

            [
                'key' =>
                    'session_storage',

                'name' =>
                    'Server-side session storage',

                'status' =>
                    in_array(
                        strtolower(
                            $sessionDriver
                        ),
                        [
                            'database',
                            'redis',
                            'file',
                            'memcached',
                            'dynamodb',
                        ],
                        true
                    )
                        ? 'PASS'
                        : 'INFO',

                'detail' =>
                    'Effective session driver: '
                    .(
                        $sessionDriver !== ''
                            ? $sessionDriver
                            : 'not configured'
                    )
                    .'.',
            ],

            [
                'key' =>
                    'application_debug',

                'name' =>
                    'Application debug exposure',

                'status' =>
                    (bool) config(
                        'app.debug',
                        false
                    )
                        ? 'WARN'
                        : 'PASS',

                'detail' =>
                    (bool) config(
                        'app.debug',
                        false
                    )
                        ? 'APP_DEBUG is enabled in the current runtime. This is acceptable only for controlled local development.'
                        : 'Application debug output is disabled.',
            ],

            [
                'key' =>
                    'central_content_profile',

                'name' =>
                    'Central content-protection profile',

                'status' =>
                    $contentProtection !== []
                        ? 'PASS'
                        : 'INFO',

                'detail' =>
                    $contentProtection !== []
                        ? 'A centralized content-protection profile is configured.'
                        : 'The central content-protection config is currently empty; protected media controls remain enforced by their owning modules.',
            ],
        ];

        $summary = [
            'pass' => 0,
            'warn' => 0,
            'info' => 0,
        ];

        foreach ($checks as $check) {
            $key = strtolower(
                (string) $check[
                    'status'
                ]
            );

            if (
                array_key_exists(
                    $key,
                    $summary
                )
            ) {
                $summary[$key]++;
            }
        }

        return [
            'checks' =>
                $checks,

            'summary' =>
                $summary,

            'stats' =>
                $this->securityStats(),

            'runtime' => [
                'environment' =>
                    (string) config(
                        'app.env',
                        'unknown'
                    ),

                'session_driver' =>
                    $sessionDriver,

                'session_lifetime_minutes' =>
                    (int) config(
                        'session.lifetime',
                        0
                    ),

                'kyc_disk' =>
                    $kycDiskName,
            ],
        ];
    }

    private function securityStats(): array
    {
        return [
            'active_sessions' =>
                DB::table(
                    'user_refresh_tokens'
                )
                    ->whereNull(
                        'revoked_at'
                    )
                    ->where(
                        'expires_at',
                        '>',
                        now()
                    )
                    ->count(),

            'trusted_devices' =>
                DB::table(
                    'user_devices'
                )
                    ->where(
                        'is_active',
                        true
                    )
                    ->where(
                        'is_trusted',
                        true
                    )
                    ->whereNull(
                        'revoked_at'
                    )
                    ->count(),

            'totp_users' =>
                DB::table(
                    'users'
                )
                    ->whereNotNull(
                        'totp_confirmed_at'
                    )
                    ->count(),

            'revoked_devices_24h' =>
                DB::table(
                    'user_devices'
                )
                    ->whereNotNull(
                        'revoked_at'
                    )
                    ->where(
                        'revoked_at',
                        '>=',
                        now()->subDay()
                    )
                    ->count(),

            'security_events_24h' =>
                $this->securityEventsQuery()
                    ->where(
                        'created_at',
                        '>=',
                        now()->subDay()
                    )
                    ->count(),
        ];
    }

    private function securityEventsQuery(): Builder
    {
        return AuditLog::query()
            ->where(
                function (
                    Builder $query
                ): void {
                    $query
                        ->whereIn(
                            'module',
                            [
                                'AUTH',
                                'SECURITY',
                                'DEVICE_SECURITY',
                                'KYC',
                            ]
                        )
                        ->orWhere(
                            function (
                                Builder $actions
                            ): void {
                                foreach (
                                    [
                                        '%LOGIN%',
                                        '%LOGOUT%',
                                        '%PASSWORD%',
                                        '%TOTP%',
                                        '%OTP%',
                                        '%SESSION%',
                                        '%DEVICE%',
                                        '%QUICK_UNLOCK%',
                                        '%SOCIAL%',
                                        '%SECURITY%',
                                        '%_ACCESSED',
                                    ]
                                    as $pattern
                                ) {
                                    $actions->orWhere(
                                        'action',
                                        'like',
                                        $pattern
                                    );
                                }
                            }
                        );
                }
            );
    }

    private function sessionQuery(): QueryBuilder
    {
        return DB::table(
            'user_refresh_tokens as urt'
        )
            ->join(
                'users as u',
                'u.id',
                '=',
                'urt.user_id'
            )
            ->join(
                'user_devices as d',
                'd.id',
                '=',
                'urt.user_device_id'
            )
            ->select([
                'urt.id',
                'urt.user_id',
                'urt.user_device_id',
                'urt.expires_at',
                'urt.last_used_at',
                'urt.revoked_at',
                'urt.created_at as token_created_at',

                'u.uuid as user_uuid',
                'u.name as user_name',
                'u.email as user_email',
                'u.status as user_status',

                'd.uuid as device_uuid',
                'd.device_slot',
                'd.platform',
                'd.device_name',
                'd.device_model',
                'd.os_version',
                'd.app_version',
                'd.is_trusted',
                'd.is_active',
                'd.last_seen_at',
                'd.last_login_at',
                'd.last_login_ip',
                'd.revoked_at as device_revoked_at',
            ]);
    }

    private function applyEventFilters(
        Builder $query,
        array $filters
    ): void {
        $search = trim(
            (string) (
                $filters[
                    'search'
                ] ?? ''
            )
        );

        if ($search !== '') {
            $query->where(
                function (
                    Builder $builder
                ) use (
                    $search
                ): void {
                    $builder
                        ->where(
                            'action',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'module',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'actor_type',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'target_type',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'ip_address',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'user_agent',
                            'like',
                            '%'.$search.'%'
                        );

                    if (
                        ctype_digit(
                            $search
                        )
                    ) {
                        $builder
                            ->orWhere(
                                'actor_id',
                                (int) $search
                            )
                            ->orWhere(
                                'target_id',
                                (int) $search
                            );
                    }
                }
            );
        }

        $action = trim(
            (string) (
                $filters[
                    'action'
                ] ?? ''
            )
        );

        if ($action !== '') {
            $query->where(
                'action',
                $action
            );
        }

        $module = trim(
            (string) (
                $filters[
                    'module'
                ] ?? ''
            )
        );

        if ($module !== '') {
            $query->where(
                'module',
                $module
            );
        }

        $actorType = strtoupper(
            trim(
                (string) (
                    $filters[
                        'actor_type'
                    ] ?? ''
                )
            )
        );

        if (
            in_array(
                $actorType,
                [
                    'ADMIN',
                    'USER',
                    'SYSTEM',
                ],
                true
            )
        ) {
            $query->where(
                'actor_type',
                $actorType
            );
        }

        $from = trim(
            (string) (
                $filters[
                    'from'
                ] ?? ''
            )
        );

        if ($from !== '') {
            $query->whereDate(
                'created_at',
                '>=',
                $from
            );
        }

        $to = trim(
            (string) (
                $filters[
                    'to'
                ] ?? ''
            )
        );

        if ($to !== '') {
            $query->whereDate(
                'created_at',
                '<=',
                $to
            );
        }
    }

    private function applySessionFilters(
        QueryBuilder $query,
        array $filters
    ): void {
        $search = trim(
            (string) (
                $filters[
                    'search'
                ] ?? ''
            )
        );

        if ($search !== '') {
            $query->where(
                function (
                    QueryBuilder $builder
                ) use (
                    $search
                ): void {
                    $builder
                        ->where(
                            'u.uuid',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'u.name',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'u.email',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'd.uuid',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'd.device_name',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'd.device_model',
                            'like',
                            '%'.$search.'%'
                        );
                }
            );
        }

        $status = strtoupper(
            trim(
                (string) (
                    $filters[
                        'status'
                    ] ?? ''
                )
            )
        );

        if ($status === self::SESSION_ACTIVE) {
            $query
                ->whereNull(
                    'urt.revoked_at'
                )
                ->where(
                    'urt.expires_at',
                    '>',
                    now()
                );
        } elseif (
            $status === self::SESSION_EXPIRED
        ) {
            $query
                ->whereNull(
                    'urt.revoked_at'
                )
                ->where(
                    'urt.expires_at',
                    '<=',
                    now()
                );
        } elseif (
            $status === self::SESSION_REVOKED
        ) {
            $query->whereNotNull(
                'urt.revoked_at'
            );
        }

        $slot = strtoupper(
            trim(
                (string) (
                    $filters[
                        'slot'
                    ] ?? ''
                )
            )
        );

        if (
            in_array(
                $slot,
                [
                    UserDevice::SLOT_MOBILE,
                    UserDevice::SLOT_DESKTOP,
                ],
                true
            )
        ) {
            $query->where(
                'd.device_slot',
                $slot
            );
        }

        $platform = strtoupper(
            trim(
                (string) (
                    $filters[
                        'platform'
                    ] ?? ''
                )
            )
        );

        if (
            in_array(
                $platform,
                [
                    UserDevice::PLATFORM_ANDROID,
                    UserDevice::PLATFORM_IOS,
                    UserDevice::PLATFORM_WINDOWS,
                    UserDevice::PLATFORM_MACOS,
                    UserDevice::PLATFORM_WEB,
                ],
                true
            )
        ) {
            $query->where(
                'd.platform',
                $platform
            );
        }

        $from = trim(
            (string) (
                $filters[
                    'from'
                ] ?? ''
            )
        );

        if ($from !== '') {
            $query->whereDate(
                'urt.created_at',
                '>=',
                $from
            );
        }

        $to = trim(
            (string) (
                $filters[
                    'to'
                ] ?? ''
            )
        );

        if ($to !== '') {
            $query->whereDate(
                'urt.created_at',
                '<=',
                $to
            );
        }
    }

    private function sessionStatus(
        mixed $revokedAt,
        mixed $expiresAt
    ): string {
        if ($revokedAt !== null) {
            return self::SESSION_REVOKED;
        }

        if (
            $expiresAt === null
            || now()->greaterThanOrEqualTo(
                \Illuminate\Support\Carbon::parse(
                    $expiresAt
                )
            )
        ) {
            return self::SESSION_EXPIRED;
        }

        return self::SESSION_ACTIVE;
    }

    private function isPrivateDisk(
        array $disk,
        bool $requireServeOff = true
    ): bool {
        if ($disk === []) {
            return false;
        }

        if (
            strtolower(
                (string) (
                    $disk[
                        'visibility'
                    ] ?? ''
                )
            ) === 'public'
        ) {
            return false;
        }

        if (
            array_key_exists(
                'url',
                $disk
            )
            && filled(
                $disk[
                    'url'
                ]
            )
        ) {
            return false;
        }

        if (
            $requireServeOff
            && (
                $disk[
                    'serve'
                ] ?? false
            ) !== false
        ) {
            return false;
        }

        return true;
    }

    private function perPage(
        array $filters
    ): int {
        $requested = (int) (
            $filters[
                'per_page'
            ] ?? 20
        );

        return in_array(
            $requested,
            [
                10,
                20,
                50,
                100,
            ],
            true
        )
            ? $requested
            : 20;
    }
}
