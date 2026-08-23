<?php

namespace App\Services\Profile;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialProfileService
{
    private const FIELDS = [
        'paypal_email',
        'binance_email',
        'usdt_trc20_address',
        'bank_name',
        'bank_branch',
        'bank_account_holder',
        'bank_account_number',
    ];

    public function __construct(
        private readonly TotpService
            $totpService
    ) {
    }

    public function update(
        User $user,
        array $data,
        Request $request
    ): UserProfile {
        $payload =
            collect(
                self::FIELDS
            )
                ->filter(
                    fn (
                        string $field
                    ): bool =>
                        array_key_exists(
                            $field,
                            $data
                        )
                )
                ->mapWithKeys(
                    fn (
                        string $field
                    ): array => [
                        $field =>
                            $data[$field],
                    ]
                )
                ->all();

        if ($payload === []) {
            throw new AuthApiException(
                422,
                'NO_FINANCIAL_FIELDS',
                'Provide at least one financial profile field to update.'
            );
        }

        $this->validateBankCountry(
            $user,
            $payload
        );

        return DB::transaction(
            function () use (
                $user,
                $data,
                $payload,
                $request
            ): UserProfile {
                $this->totpService
                    ->consume(
                        $user,
                        $data['totp_code']
                    );

                $profile =
                    UserProfile::query()
                        ->firstOrNew([
                            'user_id' =>
                                $user->getKey(),
                        ]);

                $profile->forceFill(
                    array_merge(
                        $payload,
                        [
                            'financial_updated_at' =>
                                now(),
                        ]
                    )
                )->save();

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'PROFILE_FINANCIAL_UPDATED',
                    'PROFILE',
                    'USER_PROFILE',
                    $profile->getKey(),
                    null,
                    [
                        'changed_fields' =>
                            array_keys(
                                $payload
                            ),
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return $profile;
            }
        );
    }

    private function validateBankCountry(
        User $user,
        array $payload
    ): void {
        $bankFields = [
            'bank_name',
            'bank_branch',
            'bank_account_holder',
            'bank_account_number',
        ];

        $hasBankContent =
            collect(
                $bankFields
            )
                ->contains(
                    fn (
                        string $field
                    ): bool =>
                        array_key_exists(
                            $field,
                            $payload
                        )
                        && filled(
                            $payload[$field]
                        )
                );

        if ($hasBankContent) {
            $allBankFieldsPresent =
                collect(
                    $bankFields
                )
                    ->every(
                        fn (
                            string $field
                        ): bool =>
                            array_key_exists(
                                $field,
                                $payload
                            )
                            && filled(
                                $payload[$field]
                            )
                    );

            if (! $allBankFieldsPresent) {
                throw new AuthApiException(
                    422,
                    'BANK_DETAILS_INCOMPLETE',
                    'Bank name, branch, account holder, and account number must be provided together.'
                );
            }

            if ($user->country_code !== 'LK') {
                throw new AuthApiException(
                    422,
                    'LOCAL_BANK_NOT_AVAILABLE',
                    'Sri Lanka local-bank details are only available for Sri Lanka accounts.'
                );
            }
        }
    }
}
