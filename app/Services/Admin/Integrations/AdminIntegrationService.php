<?php

namespace App\Services\Admin\Integrations;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\IntegrationProviderState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminIntegrationService
{
    public const PROVIDERS = [
        'PAYPAL',
        'STRIPE',
        'NOWPAYMENTS',
        'LOCAL_BANK',
    ];

    public function index(): array
    {
        $states =
            IntegrationProviderState::query()
                ->with([
                    'updatedByAdmin:id,name,email',
                ])
                ->whereIn(
                    'provider_code',
                    self::PROVIDERS
                )
                ->get()
                ->keyBy(
                    'provider_code'
                );

        $providers = [];

        foreach (
            self::PROVIDERS
            as $providerCode
        ) {
            $providers[] =
                $this->snapshot(
                    $providerCode,
                    $states->get(
                        $providerCode
                    )
                );
        }

        return [
            'providers' =>
                $providers,

            'summary' => [
                'total' =>
                    count(
                        $providers
                    ),

                'configured' =>
                    collect(
                        $providers
                    )
                        ->where(
                            'configured',
                            true
                        )
                        ->count(),

                'control_enabled' =>
                    collect(
                        $providers
                    )
                        ->where(
                            'control_enabled',
                            true
                        )
                        ->count(),

                'runtime_existing' =>
                    collect(
                        $providers
                    )
                        ->where(
                            'runtime_adapter_status',
                            'ACTIVE_EXISTING'
                        )
                        ->count(),
            ],
        ];
    }

    public function show(
        string $providerCode
    ): array {
        $providerCode =
            $this->normalizeProviderCode(
                $providerCode
            );

        $state =
            IntegrationProviderState::query()
                ->with([
                    'updatedByAdmin:id,name,email',
                ])
                ->where(
                    'provider_code',
                    $providerCode
                )
                ->first();

        return [
            'provider' =>
                $this->snapshot(
                    $providerCode,
                    $state
                ),
        ];
    }

    public function update(
        AdminUser $admin,
        string $providerCode,
        array $data,
        Request $request
    ): array {
        $providerCode =
            $this->normalizeProviderCode(
                $providerCode
            );

        $definition =
            $this->definition(
                $providerCode
            );

        $environment =
            strtoupper(
                (string) $data[
                    'environment'
                ]
            );

        if (
            ! in_array(
                $environment,
                $definition[
                    'environment_options'
                ],
                true
            )
        ) {
            abort(
                422,
                'The selected environment is not valid for this provider.'
            );
        }

        $countries =
            $this->normalizeCountries(
                $data['countries']
                ?? []
            );

        $isEnabled =
            (bool) $data[
                'is_enabled'
            ];

        $eligibilityConfirmed =
            (bool) $data[
                'eligibility_confirmed'
            ];

        $readiness =
            $this->configurationReadiness(
                $providerCode
            );

        if ($isEnabled) {
            if ($countries === []) {
                abort(
                    422,
                    'At least one explicitly approved country is required before enabling an integration.'
                );
            }

            if (! $eligibilityConfirmed) {
                abort(
                    422,
                    'Merchant/provider eligibility must be explicitly confirmed before enabling an integration.'
                );
            }

            if (! $readiness['configured']) {
                abort(
                    409,
                    'This provider cannot be enabled because required server-side configuration is incomplete.'
                );
            }
        }

        return DB::transaction(
            function () use (
                $admin,
                $providerCode,
                $environment,
                $countries,
                $isEnabled,
                $eligibilityConfirmed,
                $data,
                $request
            ): array {
                IntegrationProviderState::query()
                    ->insertOrIgnore([
                        'provider_code' =>
                            $providerCode,

                        'is_enabled' =>
                            false,

                        'environment' =>
                            $environment,

                        'countries' =>
                            json_encode(
                                [],
                                JSON_THROW_ON_ERROR
                            ),

                        'eligibility_confirmed' =>
                            false,

                        'version' =>
                            0,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                $state =
                    IntegrationProviderState::query()
                        ->where(
                            'provider_code',
                            $providerCode
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $submittedVersion =
                    (int) $data[
                        'version'
                    ];

                if (
                    $state->version
                    !== $submittedVersion
                ) {
                    abort(
                        409,
                        'Integration settings changed in another session. Refresh and try again.'
                    );
                }

                $old =
                    $this->safeState(
                        $state
                    );

                $state->forceFill([
                    'is_enabled' =>
                        $isEnabled,

                    'environment' =>
                        $environment,

                    'countries' =>
                        $countries,

                    'eligibility_confirmed' =>
                        $eligibilityConfirmed,

                    'version' =>
                        $state->version + 1,

                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType:
                        'ADMIN',
                    actorId:
                        $admin->getKey(),
                    action:
                        'INTEGRATION_PROVIDER_STATE_UPDATED',
                    module:
                        'INTEGRATIONS',
                    targetType:
                        'INTEGRATION_PROVIDER',
                    targetId:
                        $state->getKey(),
                    oldValues:
                        $old,
                    newValues: [
                        ...$this->safeState(
                            $state
                        ),

                        'change_reason_provided' =>
                            trim(
                                (string) $data[
                                    'change_reason'
                                ]
                            ) !== '',
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );

                return $this->snapshot(
                    $providerCode,
                    $state->fresh(
                        'updatedByAdmin'
                    )
                );
            },
            3
        );
    }

    public function normalizeProviderCode(
        string $providerCode
    ): string {
        $providerCode =
            strtoupper(
                trim(
                    $providerCode
                )
            );

        abort_unless(
            in_array(
                $providerCode,
                self::PROVIDERS,
                true
            ),
            404
        );

        return $providerCode;
    }

    private function snapshot(
        string $providerCode,
        ?IntegrationProviderState $state
    ): array {
        $definition =
            $this->definition(
                $providerCode
            );

        $readiness =
            $this->configurationReadiness(
                $providerCode
            );

        $source =
            $this->sourceRuntime(
                $providerCode
            );

        $environment =
            $state?->environment
            ?: $source['environment'];

        $countries =
            $state
                ? $this->normalizeCountries(
                    $state->countries
                    ?? []
                )
                : $source['countries'];

        $controlEnabled =
            (bool) (
                $state?->is_enabled
                ?? false
            );

        $eligibilityConfirmed =
            (bool) (
                $state
                    ?->eligibility_confirmed
                ?? false
            );

        return [
            'code' =>
                $providerCode,

            'display_name' =>
                $definition[
                    'display_name'
                ],

            'kind' =>
                $definition['kind'],

            'transaction_owner' =>
                $definition[
                    'transaction_owner'
                ],

            'runtime_adapter_status' =>
                $definition[
                    'runtime_adapter_status'
                ],

            'environment_options' =>
                $definition[
                    'environment_options'
                ],

            'configured' =>
                $readiness[
                    'configured'
                ],

            'configuration_checks' =>
                $readiness[
                    'checks'
                ],

            'source_runtime_enabled' =>
                $source['enabled'],

            'source_runtime_countries' =>
                $source['countries'],

            'source_runtime_environment' =>
                $source[
                    'environment'
                ],

            'control_enabled' =>
                $controlEnabled,

            'eligibility_confirmed' =>
                $eligibilityConfirmed,

            'environment' =>
                $environment,

            'countries' =>
                $countries,

            'version' =>
                (int) (
                    $state?->version
                    ?? 0
                ),

            'updated_by' =>
                $state
                    ?->updatedByAdmin
                    ?->name,

            'updated_at' =>
                $state
                    ?->updated_at,

            'control_status' =>
                $this->controlStatus(
                    controlEnabled:
                        $controlEnabled,
                    eligibilityConfirmed:
                        $eligibilityConfirmed,
                    configured:
                        $readiness[
                            'configured'
                        ],
                    runtimeAdapterStatus:
                        $definition[
                            'runtime_adapter_status'
                        ]
                ),

            'runtime_status' =>
                $this->runtimeStatus(
                    $source[
                        'enabled'
                    ],
                    $readiness[
                        'configured'
                    ],
                    $definition[
                        'runtime_adapter_status'
                    ]
                ),
        ];
    }

    private function definition(
        string $providerCode
    ): array {
        $definition =
            config(
                'tradex_integrations.providers.'
                .$providerCode
            );

        abort_unless(
            is_array(
                $definition
            ),
            404
        );

        return $definition;
    }

    private function configurationReadiness(
        string $providerCode
    ): array {
        return match (
            $providerCode
        ) {
            'PAYPAL' =>
                $this->paypalReadiness(),

            'STRIPE' => [
                'configured' =>
                    filled(
                        config(
                            'tradex_integrations.stripe.publishable_key'
                        )
                    )
                    && filled(
                        config(
                            'tradex_integrations.stripe.secret_key'
                        )
                    )
                    && filled(
                        config(
                            'tradex_integrations.stripe.webhook_secret'
                        )
                    ),

                'checks' => [
                    'Publishable key configured' =>
                        filled(
                            config(
                                'tradex_integrations.stripe.publishable_key'
                            )
                        ),

                    'Secret key configured' =>
                        filled(
                            config(
                                'tradex_integrations.stripe.secret_key'
                            )
                        ),

                    'Webhook signing secret configured' =>
                        filled(
                            config(
                                'tradex_integrations.stripe.webhook_secret'
                            )
                        ),
                ],
            ],

            'NOWPAYMENTS' => [
                'configured' =>
                    filled(
                        config(
                            'tradex_integrations.nowpayments.api_key'
                        )
                    )
                    && filled(
                        config(
                            'tradex_integrations.nowpayments.ipn_secret'
                        )
                    ),

                'checks' => [
                    'API key configured' =>
                        filled(
                            config(
                                'tradex_integrations.nowpayments.api_key'
                            )
                        ),

                    'IPN secret configured' =>
                        filled(
                            config(
                                'tradex_integrations.nowpayments.ipn_secret'
                            )
                        ),
                ],
            ],

            'LOCAL_BANK' =>
                $this->localBankReadiness(),

            default =>
                abort(
                    404
                ),
        };
    }

    private function paypalReadiness(): array
    {
        $driver =
            strtolower(
                trim(
                    (string) config(
                        'tradex_payments.paypal.driver',
                        'http'
                    )
                )
            );

        if ($driver === 'srmklive') {
            $mode =
                strtolower(
                    trim(
                        (string) config(
                            'paypal.mode',
                            'sandbox'
                        )
                    )
                );

            $clientConfigured =
                filled(
                    config(
                        'paypal.'
                        .$mode
                        .'.client_id'
                    )
                );

            $secretConfigured =
                filled(
                    config(
                        'paypal.'
                        .$mode
                        .'.client_secret'
                    )
                );
        } else {
            $clientConfigured =
                filled(
                    config(
                        'tradex_payments.paypal.client_id'
                    )
                );

            $secretConfigured =
                filled(
                    config(
                        'tradex_payments.paypal.client_secret'
                    )
                );
        }

        return [
            'configured' =>
                $clientConfigured
                && $secretConfigured,

            'checks' => [
                'Client identifier configured' =>
                    $clientConfigured,

                'Client secret configured' =>
                    $secretConfigured,

                'Webhook identifier configured' =>
                    filled(
                        config(
                            'tradex_payments.paypal.webhook_id'
                        )
                    ),
            ],
        ];
    }

    private function localBankReadiness(): array
    {
        $amount =
            (int) config(
                'tradex_payments.product.education_premium.local_bank.amount_minor',
                0
            );

        $bank =
            filled(
                config(
                    'tradex_payments.local_bank.bank_name'
                )
            );

        $branch =
            filled(
                config(
                    'tradex_payments.local_bank.branch'
                )
            );

        $holder =
            filled(
                config(
                    'tradex_payments.local_bank.account_holder'
                )
            );

        $account =
            filled(
                config(
                    'tradex_payments.local_bank.account_number'
                )
            );

        return [
            'configured' =>
                $amount > 0
                && $bank
                && $branch
                && $holder
                && $account,

            'checks' => [
                'Product LKR amount configured' =>
                    $amount > 0,

                'Bank name configured' =>
                    $bank,

                'Branch configured' =>
                    $branch,

                'Account holder configured' =>
                    $holder,

                'Account number configured' =>
                    $account,
            ],
        ];
    }

    private function sourceRuntime(
        string $providerCode
    ): array {
        return match (
            $providerCode
        ) {
            'PAYPAL' => [
                'enabled' =>
                    (bool) config(
                        'tradex_payments.paypal.enabled',
                        false
                    ),

                'countries' =>
                    $this->normalizeCountries(
                        (array) config(
                            'tradex_payments.paypal.countries',
                            []
                        )
                    ),

                'environment' =>
                    strtoupper(
                        (string) config(
                            'tradex_payments.paypal.environment',
                            'SANDBOX'
                        )
                    ),
            ],

            'STRIPE' => [
                'enabled' =>
                    (bool) config(
                        'tradex_integrations.stripe.enabled',
                        false
                    ),

                'countries' =>
                    $this->normalizeCountries(
                        (array) config(
                            'tradex_integrations.stripe.countries',
                            []
                        )
                    ),

                'environment' =>
                    strtoupper(
                        (string) config(
                            'tradex_integrations.stripe.environment',
                            'SANDBOX'
                        )
                    ),
            ],

            'NOWPAYMENTS' => [
                'enabled' =>
                    (bool) config(
                        'tradex_integrations.nowpayments.enabled',
                        false
                    ),

                'countries' =>
                    $this->normalizeCountries(
                        (array) config(
                            'tradex_integrations.nowpayments.countries',
                            []
                        )
                    ),

                'environment' =>
                    strtoupper(
                        (string) config(
                            'tradex_integrations.nowpayments.environment',
                            'SANDBOX'
                        )
                    ),
            ],

            'LOCAL_BANK' => [
                'enabled' =>
                    (bool) config(
                        'tradex_payments.local_bank.enabled',
                        false
                    ),

                'countries' =>
                    $this->normalizeCountries(
                        (array) config(
                            'tradex_payments.local_bank.countries',
                            []
                        )
                    ),

                'environment' =>
                    'MANUAL',
            ],

            default =>
                abort(
                    404
                ),
        };
    }

    private function normalizeCountries(
        array $countries
    ): array {
        $normalized = [];

        foreach (
            $countries
            as $country
        ) {
            $country =
                strtoupper(
                    trim(
                        (string) $country
                    )
                );

            if (
                preg_match(
                    '/^[A-Z]{2}$/',
                    $country
                ) !== 1
            ) {
                continue;
            }

            $normalized[] =
                $country;
        }

        return array_values(
            array_unique(
                $normalized
            )
        );
    }

    private function controlStatus(
        bool $controlEnabled,
        bool $eligibilityConfirmed,
        bool $configured,
        string $runtimeAdapterStatus
    ): string {
        if (! $controlEnabled) {
            return 'DISABLED';
        }

        if (! $eligibilityConfirmed) {
            return 'ELIGIBILITY_CONFIRMATION_REQUIRED';
        }

        if (! $configured) {
            return 'CONFIGURATION_REQUIRED';
        }

        return $runtimeAdapterStatus
            === 'ACTIVE_EXISTING'
                ? 'APPROVED_EXISTING_RUNTIME'
                : 'APPROVED_NOT_WIRED';
    }

    private function runtimeStatus(
        bool $sourceEnabled,
        bool $configured,
        string $runtimeAdapterStatus
    ): string {
        if (
            $runtimeAdapterStatus
            !== 'ACTIVE_EXISTING'
        ) {
            return 'NO_TRANSACTION_ADAPTER';
        }

        if (! $sourceEnabled) {
            return 'SOURCE_DISABLED';
        }

        if (! $configured) {
            return 'SOURCE_CONFIGURATION_REQUIRED';
        }

        return 'ACTIVE_EXISTING_RUNTIME';
    }

    private function safeState(
        IntegrationProviderState $state
    ): array {
        return [
            'provider_code' =>
                $state->provider_code,

            'is_enabled' =>
                (bool) $state
                    ->is_enabled,

            'environment' =>
                $state->environment,

            'countries' =>
                $this->normalizeCountries(
                    $state->countries
                    ?? []
                ),

            'eligibility_confirmed' =>
                (bool) $state
                    ->eligibility_confirmed,

            'version' =>
                (int) $state->version,
        ];
    }
}
