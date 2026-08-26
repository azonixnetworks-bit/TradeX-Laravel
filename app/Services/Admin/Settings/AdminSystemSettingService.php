<?php

namespace App\Services\Admin\Settings;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminSystemSettingService
{
    public function general(): array
    {
        $definitions =
            $this->generalDefinitions();

        $rows =
            SystemSetting::query()
                ->whereIn(
                    'setting_key',
                    array_keys(
                        $definitions
                    )
                )
                ->get()
                ->keyBy(
                    'setting_key'
                );

        $values = [];
        $versions = [];

        foreach (
            $definitions
            as $key => $definition
        ) {
            $row = $rows->get(
                $key
            );

            $field =
                $definition['field'];

            $values[$field] =
                $row
                    ? $this->decode(
                        $row->value,
                        $definition['type']
                    )
                    : $definition['default'];

            $versions[$field] =
                $row
                    ? (int) $row->version
                    : 0;
        }

        return [
            'values' =>
                $values,

            'versions' =>
                $versions,
        ];
    }

    public function updateGeneral(
        AdminUser $admin,
        array $data,
        Request $request
    ): array {
        $definitions =
            $this->generalDefinitions();

        return DB::transaction(
            function () use (
                $admin,
                $data,
                $request,
                $definitions
            ): array {
                $this->ensureRowsForUpdate(
                    $definitions
                );

                $rows =
                    SystemSetting::query()
                        ->whereIn(
                            'setting_key',
                            array_keys(
                                $definitions
                            )
                        )
                        ->orderBy(
                            'setting_key'
                        )
                        ->lockForUpdate()
                        ->get()
                        ->keyBy(
                            'setting_key'
                        );

                $changedKeys = [];
                $newVersions = [];

                foreach (
                    $definitions
                    as $key => $definition
                ) {
                    /** @var SystemSetting|null $row */
                    $row =
                        $rows->get(
                            $key
                        );

                    abort_unless(
                        $row instanceof SystemSetting,
                        409,
                        'The settings registry changed. Refresh and try again.'
                    );

                    $field =
                        $definition['field'];

                    $submittedVersion =
                        (int) (
                            $data['versions'][
                                $field
                            ] ?? -1
                        );

                    if (
                        $submittedVersion
                        !== (int) $row->version
                    ) {
                        abort(
                            409,
                            'These settings were changed in another session. Refresh before saving.'
                        );
                    }

                    $normalized =
                        $this->normalizeForStorage(
                            $field,
                            $data[$field] ?? null,
                            $definition['type'],
                            $data
                        );

                    if (
                        $row->value
                        !== $normalized
                    ) {
                        $row->forceFill([
                            'value' =>
                                $normalized,

                            'value_type' =>
                                $definition['type'],

                            'version' =>
                                ((int) $row->version)
                                + 1,

                            'updated_by_admin_id' =>
                                $admin->getKey(),
                        ])->save();

                        $changedKeys[] =
                            $key;
                    }

                    $newVersions[$key] =
                        (int) $row->version;
                }

                if ($changedKeys !== []) {
                    /*
                     * Deliberately audit only setting identifiers + versions.
                     * Support contact values and notice content are not copied
                     * into the audit stream.
                     */
                    AuditLog::record(
                        actorType:
                            'ADMIN',
                        actorId:
                            $admin->getKey(),
                        action:
                            'SYSTEM_SETTINGS_UPDATED',
                        module:
                            'SETTINGS',
                        targetType:
                            'SYSTEM_SETTINGS',
                        targetId:
                            null,
                        newValues: [
                            'changed_keys' =>
                                $changedKeys,

                            'versions' =>
                                array_intersect_key(
                                    $newVersions,
                                    array_flip(
                                        $changedKeys
                                    )
                                ),
                        ],
                        ipAddress:
                            $request->ip(),
                        userAgent:
                            $request->userAgent()
                    );
                }

                return [
                    'changed_keys' =>
                        $changedKeys,
                ];
            },
            3
        );
    }

    public function affiliateSnapshot(): array
    {
        return [
            'owner' =>
                'ADMIN-06 Affiliate Management',

            'commercial_policy' =>
                'Read-only here. Affiliate stages, commissions and special commission controls remain owned by the frozen Affiliate Management module.',

            'api_pagination_default' =>
                (int) config(
                    'tradex_affiliate_api.pagination.default_per_page',
                    20
                ),

            'api_pagination_max' =>
                (int) config(
                    'tradex_affiliate_api.pagination.max_per_page',
                    50
                ),
        ];
    }

    public function paymentSnapshot(): array
    {
        return [
            'paypal' => [
                'enabled' =>
                    (bool) config(
                        'tradex_payments.paypal.enabled',
                        false
                    ),

                'environment' =>
                    (string) config(
                        'tradex_payments.paypal.environment',
                        'unknown'
                    ),

                'countries' =>
                    $this->safeCountryList(
                        config(
                            'tradex_payments.paypal.countries',
                            []
                        )
                    ),

                'credentials_configured' =>
                    filled(
                        config(
                            'tradex_payments.paypal.client_id'
                        )
                    )
                    && filled(
                        config(
                            'tradex_payments.paypal.client_secret'
                        )
                    )
                    && filled(
                        config(
                            'tradex_payments.paypal.webhook_id'
                        )
                    ),
            ],

            'local_bank' => [
                'enabled' =>
                    (bool) config(
                        'tradex_payments.local_bank.enabled',
                        false
                    ),

                'countries' =>
                    $this->safeCountryList(
                        config(
                            'tradex_payments.local_bank.countries',
                            []
                        )
                    ),

                'destination_configured' =>
                    filled(
                        config(
                            'tradex_payments.local_bank.bank_name'
                        )
                    )
                    && filled(
                        config(
                            'tradex_payments.local_bank.account_holder'
                        )
                    )
                    && filled(
                        config(
                            'tradex_payments.local_bank.account_number'
                        )
                    ),
            ],

            'usdt_trc20' => [
                'enabled' =>
                    (bool) config(
                        'tradex_payments.usdt_trc20.enabled',
                        false
                    ),

                'countries' =>
                    $this->safeCountryList(
                        config(
                            'tradex_payments.usdt_trc20.countries',
                            []
                        )
                    ),

                'network' =>
                    (string) config(
                        'tradex_payments.usdt_trc20.network',
                        'TRC20'
                    ),

                'wallet_configured' =>
                    filled(
                        config(
                            'tradex_payments.usdt_trc20.wallet_address'
                        )
                    ),
            ],
        ];
    }

    public function securitySnapshot(): array
    {
        return [
            'environment' =>
                (string) config(
                    'app.env',
                    'unknown'
                ),

            'debug_enabled' =>
                (bool) config(
                    'app.debug',
                    false
                ),

            'session_driver' =>
                (string) config(
                    'session.driver',
                    'unknown'
                ),

            'session_http_only' =>
                (bool) config(
                    'session.http_only',
                    true
                ),

            'session_secure' =>
                config(
                    'session.secure'
                ) === true,

            'session_same_site' =>
                (string) config(
                    'session.same_site',
                    'unknown'
                ),

            'content_protection_profile_present' =>
                (array) config(
                    'content-protection',
                    []
                ) !== [],
        ];
    }

    public function videoSnapshot(): array
    {
        $variants =
            collect(
                config(
                    'tradex.media.hls_variants',
                    []
                )
            )
                ->filter(
                    fn ($variant): bool =>
                        is_array(
                            $variant
                        )
                )
                ->map(
                    fn (array $variant): array => [
                        'name' =>
                            (string) (
                                $variant['name']
                                ?? 'unknown'
                            ),

                        'height' =>
                            (int) (
                                $variant['height']
                                ?? 0
                            ),
                    ]
                )
                ->values()
                ->all();

        return [
            'queue' =>
                (string) config(
                    'tradex.media.queue',
                    'video-processing'
                ),

            'max_upload_kb' =>
                (int) config(
                    'tradex.media.max_upload_kb',
                    0
                ),

            'processing_timeout_seconds' =>
                (int) config(
                    'tradex.media.processing_timeout_seconds',
                    0
                ),

            'hls_segment_seconds' =>
                (int) config(
                    'tradex.media.hls_segment_seconds',
                    0
                ),

            'variants' =>
                $variants,
        ];
    }

    private function ensureRowsForUpdate(
        array $definitions
    ): void {
        $now = now();
        $rows = [];

        foreach (
            $definitions
            as $key => $definition
        ) {
            $rows[] = [
                'setting_key' =>
                    $key,

                'group_name' =>
                    'GENERAL',

                'value' =>
                    $this->encodeDefault(
                        $definition['default'],
                        $definition['type']
                    ),

                'value_type' =>
                    $definition['type'],

                'version' =>
                    0,

                'updated_by_admin_id' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ];
        }

        DB::table(
            'system_settings'
        )->insertOrIgnore(
            $rows
        );
    }

    private function generalDefinitions(): array
    {
        $definitions =
            config(
                'tradex_settings.general',
                []
            );

        abort_unless(
            is_array(
                $definitions
            )
            && $definitions !== [],
            500,
            'TradeX safe settings registry is not configured.'
        );

        return $definitions;
    }

    private function normalizeForStorage(
        string $field,
        mixed $value,
        string $type,
        array $allData
    ): ?string {
        if (
            $field
            === 'maintenance_notice'
            && ! (
                $allData[
                    'maintenance_notice_enabled'
                ] ?? false
            )
        ) {
            return null;
        }

        return match ($type) {
            'BOOL' =>
                (bool) $value
                    ? '1'
                    : '0',

            'STRING' =>
                $this->nullableString(
                    $value
                ),

            default =>
                abort(
                    500,
                    'Unsupported safe setting type.'
                ),
        };
    }

    private function encodeDefault(
        mixed $value,
        string $type
    ): ?string {
        return match ($type) {
            'BOOL' =>
                (bool) $value
                    ? '1'
                    : '0',

            'STRING' =>
                $this->nullableString(
                    $value
                ),

            default =>
                null,
        };
    }

    private function decode(
        ?string $value,
        string $type
    ): mixed {
        return match ($type) {
            'BOOL' =>
                $value === '1',

            'STRING' =>
                $value,

            default =>
                null,
        };
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (
            ! is_scalar(
                $value
            )
        ) {
            return null;
        }

        $text = trim(
            (string) $value
        );

        return $text !== ''
            ? $text
            : null;
    }

    private function safeCountryList(
        mixed $value
    ): array {
        if (
            ! is_array(
                $value
            )
        ) {
            return [];
        }

        return collect(
            $value
        )
            ->filter(
                fn ($country): bool =>
                    is_string(
                        $country
                    )
                    && preg_match(
                        '/^[A-Z]{2}$/',
                        $country
                    ) === 1
            )
            ->values()
            ->all();
    }
}
