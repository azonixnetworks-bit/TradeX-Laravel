<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentReceivingAccount;
use App\Models\User;

class ManualPaymentAccountCatalogService
{
    public function __construct(
        private readonly ScaledDecimal $decimal
    ) {
    }

    public function forUser(
        User $user
    ): array {
        $accounts =
            $this
                ->eligibleQuery(
                    $user
                )
                ->get();

        return [
            'country_code' =>
                strtoupper(
                    (string) $user
                        ->country_code
                ),
            'accounts' =>
                $accounts
                    ->map(
                        fn (
                            PaymentReceivingAccount $account
                        ): array =>
                            $this
                                ->summary(
                                    $account
                                )
                    )
                    ->values()
                    ->all(),
        ];
    }

    public function resolveEligible(
        User $user,
        string $uuid
    ): PaymentReceivingAccount {
        $account =
            $this
                ->eligibleQuery(
                    $user
                )
                ->where(
                    'uuid',
                    $uuid
                )
                ->first();

        if (
            ! $account
            instanceof PaymentReceivingAccount
        ) {
            throw new AuthApiException(
                404,
                'PAYMENT_RECEIVING_ACCOUNT_NOT_AVAILABLE',
                'This payment receiving account is not available.'
            );
        }

        return $account;
    }

    private function eligibleQuery(
        User $user
    ) {
        $country =
            strtoupper(
                (string) $user
                    ->country_code
            );

        return PaymentReceivingAccount::query()
            ->where(
                'country_code',
                $country
            )
            ->where(
                'is_active',
                true
            )
            ->whereNotNull(
                'tender_amount_decimal'
            )
            ->where(
                'tender_amount_decimal',
                '!=',
                ''
            )
            ->where(
                function (
                    $query
                ): void {
                    $query->where(
                        'type',
                        PaymentReceivingAccount::TYPE_BANK
                    )
                        ->orWhere(
                            'type',
                            PaymentReceivingAccount::TYPE_CRYPTO
                        );
                }
            )
            ->orderBy(
                'display_order'
            )
            ->orderBy(
                'id'
            );
    }
    private function summary(
        PaymentReceivingAccount $account
    ): array {
        $amount =
            filled(
                $account
                    ->tender_amount_decimal
            )
                ? (string) $account
                    ->tender_amount_decimal
                : $this->decimal
                    ->formatMinor(
                        (int) $account
                            ->tender_amount_minor,
                        (int) $account
                            ->amount_scale
                    );

        $data = [
            'uuid' =>
                $account->uuid,
            'type' =>
                $account->type,
            'display_name' =>
                $account
                    ->display_name,
            'country_code' =>
                $account
                    ->country_code,
            'tender' => [
                'currency' =>
                    $account
                        ->currency,
                'amount_minor' =>
                    $account
                        ->tender_amount_minor
                        !== null
                            ? (int) $account
                                ->tender_amount_minor
                            : null,
                'scale' =>
                    (int) $account
                        ->amount_scale,
                'amount' =>
                    $amount,
            ],
        ];

        if (
            $account->isBank()
        ) {
            $data[
                'bank_name'
            ] =
                $account
                    ->bank_name;

            $data[
                'branch'
            ] =
                $account
                    ->bank_branch;
        } else {
            $data[
                'coin'
            ] =
                $account
                    ->coin_symbol;

            $data[
                'network'
            ] =
                $account
                    ->network;

            $data[
                'qr_available'
            ] =
                filled(
                    $account
                        ->qr_path
                );
        }

        return $data;
    }

}
