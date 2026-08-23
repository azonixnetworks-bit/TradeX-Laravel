<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\BankDeposit;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UsdtDeposit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ManualDepositService
{
    public function __construct(
        private readonly PaymentMethodService
            $methods,
        private readonly PaymentOrderService
            $orders,
        private readonly PaymentApiPresenter
            $presenter,
        private readonly PaymentMoney
            $money
    ) {
    }

    public function submitBankDeposit(
        User $user,
        array $data,
        UploadedFile $proof,
        Request $request
    ): array {
        $method = $this->methods
            ->assertAvailable(
                $user,
                PaymentOrder::PROVIDER_LOCAL_BANK
            );

        $idempotencyHash =
            $this->orders
                ->idempotencyHash(
                    (string) $data[
                        'idempotency_key'
                    ]
                );

        $existing =
            PaymentOrder::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where(
                    'client_idempotency_key_hash',
                    $idempotencyHash
                )
                ->first();

        if ($existing instanceof PaymentOrder) {
            $this->assertProviderMatches(
                $existing,
                PaymentOrder::PROVIDER_LOCAL_BANK
            );

            return $this->presenter
                ->order(
                    $existing
                );
        }

        $disk = (string) config(
            'tradex_payments.local_bank.proof_disk',
            'local'
        );

        $extension = strtolower(
            (string) (
                $proof->guessExtension()
                ?: $proof->getClientOriginalExtension()
                ?: 'bin'
            )
        );

        $path =
            'payments/bank/'
            .$user->uuid
            .'/'
            .Str::uuid()
            .'.'
            .$extension;

        $stored = Storage::disk($disk)
            ->putFileAs(
                dirname($path),
                $proof,
                basename($path)
            );

        if (! $stored) {
            throw new AuthApiException(
                503,
                'PAYMENT_PROOF_STORAGE_FAILED',
                'The bank-deposit proof could not be stored securely.'
            );
        }

        $usedStoredProof = false;

        try {
            $order = DB::transaction(
                function () use (
                    $user,
                    $data,
                    $disk,
                    $path,
                    $method,
                    $idempotencyHash,
                    $request,
                    &$usedStoredProof
                ): PaymentOrder {
                    $concurrent =
                        PaymentOrder::query()
                            ->where(
                                'user_id',
                                $user->getKey()
                            )
                            ->where(
                                'client_idempotency_key_hash',
                                $idempotencyHash
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        $concurrent
                        instanceof PaymentOrder
                    ) {
                        $this->assertProviderMatches(
                            $concurrent,
                            PaymentOrder::PROVIDER_LOCAL_BANK
                        );

                        return $concurrent;
                    }

                    $amount = (int) data_get(
                        $method,
                        'amount.amount_minor',
                        0
                    );

                    $currency = strtoupper(
                        (string) data_get(
                            $method,
                            'amount.currency',
                            'LKR'
                        )
                    );

                    $order =
                        PaymentOrder::query()
                            ->create([
                                'user_id' =>
                                    $user->getKey(),

                                'product_code' =>
                                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                                'provider' =>
                                    PaymentOrder::PROVIDER_LOCAL_BANK,

                                'country_code' =>
                                    strtoupper(
                                        (string) $user
                                            ->country_code
                                    ),

                                'currency' =>
                                    $currency,

                                'amount_minor' =>
                                    $amount,

                                'status' =>
                                    PaymentOrder::STATUS_PENDING_REVIEW,

                                'client_idempotency_key_hash' =>
                                    $idempotencyHash,
                            ]);

                    BankDeposit::query()
                        ->create([
                            'payment_order_id' =>
                                $order->getKey(),

                            'user_id' =>
                                $user->getKey(),

                            'depositor_name' =>
                                trim(
                                    (string) $data[
                                        'depositor_name'
                                    ]
                                ),

                            'bank_reference' =>
                                trim(
                                    (string) $data[
                                        'bank_reference'
                                    ]
                                ),

                            'deposit_date' =>
                                $data[
                                    'deposit_date'
                                ],

                            'proof_disk' =>
                                $disk,

                            'proof_path' =>
                                $path,

                            'status' =>
                                BankDeposit::STATUS_SUBMITTED,

                            'submitted_at' =>
                                now(),
                        ]);

                    $usedStoredProof = true;

                    AuditLog::record(
                        actorType: 'USER',
                        actorId: $user->getKey(),
                        action: 'PAYMENT_BANK_DEPOSIT_SUBMITTED',
                        module: 'PAYMENTS',
                        targetType: 'PAYMENT_ORDER',
                        targetId: $order->getKey(),
                        newValues: [
                            'order_uuid' =>
                                $order->uuid,

                            'provider' =>
                                PaymentOrder::PROVIDER_LOCAL_BANK,

                            'currency' =>
                                $currency,

                            'amount_minor' =>
                                $amount,

                            'status' =>
                                PaymentOrder::STATUS_PENDING_REVIEW,
                        ],
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );

                    return $order;
                },
                3
            );
        } catch (Throwable $exception) {
            Storage::disk($disk)
                ->delete($path);

            throw $exception;
        }

        if (! $usedStoredProof) {
            Storage::disk($disk)
                ->delete($path);
        }

        /*
        | A manual bank submission never activates Premium. It remains
        | PENDING_REVIEW until an authorized finance/admin reconciliation flow
        | explicitly clears it in a later module.
        */

        return $this->presenter
            ->order(
                $order->fresh([
                    'bankDeposit',
                    'payment',
                ])
            );
    }

    public function submitUsdtDeposit(
        User $user,
        array $data,
        Request $request
    ): array {
        $method = $this->methods
            ->assertAvailable(
                $user,
                PaymentOrder::PROVIDER_USDT_TRC20
            );

        $idempotencyHash =
            $this->orders
                ->idempotencyHash(
                    (string) $data[
                        'idempotency_key'
                    ]
                );

        $existing =
            PaymentOrder::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where(
                    'client_idempotency_key_hash',
                    $idempotencyHash
                )
                ->first();

        if ($existing instanceof PaymentOrder) {
            $this->assertProviderMatches(
                $existing,
                PaymentOrder::PROVIDER_USDT_TRC20
            );

            return $this->presenter
                ->order(
                    $existing
                );
        }

        $txHash = strtolower(
            trim(
                (string) $data[
                    'tx_hash'
                ]
            )
        );

        if (
            UsdtDeposit::query()
                ->where(
                    'tx_hash',
                    $txHash
                )
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'USDT_TRANSACTION_ALREADY_SUBMITTED',
                'This USDT transaction has already been submitted.'
            );
        }

        $currency = strtoupper(
            (string) data_get(
                $method,
                'amount.currency',
                'USDT'
            )
        );

        $expectedMinor = (int) data_get(
            $method,
            'amount.amount_minor',
            0
        );

        $submittedMinor =
            $this->money
                ->decimalToMinor(
                    (string) $data[
                        'amount'
                    ],
                    $currency
                );

        if (
            $submittedMinor
            !== $expectedMinor
        ) {
            throw new AuthApiException(
                422,
                'USDT_AMOUNT_MISMATCH',
                'The submitted USDT amount does not match the required TradeX payment amount.'
            );
        }

        $order = DB::transaction(
            function () use (
                $user,
                $data,
                $request,
                $idempotencyHash,
                $txHash,
                $currency,
                $expectedMinor
            ): PaymentOrder {
                $concurrent =
                    PaymentOrder::query()
                        ->where(
                            'user_id',
                            $user->getKey()
                        )
                        ->where(
                            'client_idempotency_key_hash',
                            $idempotencyHash
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $concurrent
                    instanceof PaymentOrder
                ) {
                    $this->assertProviderMatches(
                        $concurrent,
                        PaymentOrder::PROVIDER_USDT_TRC20
                    );

                    return $concurrent;
                }

                if (
                    UsdtDeposit::query()
                        ->where(
                            'tx_hash',
                            $txHash
                        )
                        ->lockForUpdate()
                        ->exists()
                ) {
                    throw new AuthApiException(
                        409,
                        'USDT_TRANSACTION_ALREADY_SUBMITTED',
                        'This USDT transaction has already been submitted.'
                    );
                }

                $order =
                    PaymentOrder::query()
                        ->create([
                            'user_id' =>
                                $user->getKey(),

                            'product_code' =>
                                PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                            'provider' =>
                                PaymentOrder::PROVIDER_USDT_TRC20,

                            'country_code' =>
                                strtoupper(
                                    (string) $user
                                        ->country_code
                                ),

                            'currency' =>
                                $currency,

                            'amount_minor' =>
                                $expectedMinor,

                            'status' =>
                                PaymentOrder::STATUS_PENDING_REVIEW,

                            'client_idempotency_key_hash' =>
                                $idempotencyHash,
                        ]);

                UsdtDeposit::query()
                    ->create([
                        'payment_order_id' =>
                            $order->getKey(),

                        'user_id' =>
                            $user->getKey(),

                        'network' =>
                            UsdtDeposit::NETWORK_TRC20,

                        'tx_hash' =>
                            $txHash,

                        'sender_address' =>
                            filled(
                                $data[
                                    'sender_address'
                                ]
                                ?? null
                            )
                                ? trim(
                                    (string) $data[
                                        'sender_address'
                                    ]
                                )
                                : null,

                        'amount_micro' =>
                            $expectedMinor,

                        'status' =>
                            UsdtDeposit::STATUS_SUBMITTED,

                        'submitted_at' =>
                            now(),
                    ]);

                AuditLog::record(
                    actorType: 'USER',
                    actorId: $user->getKey(),
                    action: 'PAYMENT_USDT_DEPOSIT_SUBMITTED',
                    module: 'PAYMENTS',
                    targetType: 'PAYMENT_ORDER',
                    targetId: $order->getKey(),
                    newValues: [
                        'order_uuid' =>
                            $order->uuid,

                        'provider' =>
                            PaymentOrder::PROVIDER_USDT_TRC20,

                        'currency' =>
                            $currency,

                        'amount_minor' =>
                            $expectedMinor,

                        'network' =>
                            UsdtDeposit::NETWORK_TRC20,

                        'status' =>
                            PaymentOrder::STATUS_PENDING_REVIEW,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $order;
            },
            3
        );

        /*
        | A user-provided blockchain hash is evidence only. TradeX never
        | self-clears Premium from this endpoint. Finance/admin reconciliation
        | must verify the on-chain transfer in a later module.
        */

        return $this->presenter
            ->order(
                $order->fresh([
                    'usdtDeposit',
                    'payment',
                ])
            );
    }

    private function assertProviderMatches(
        PaymentOrder $order,
        string $expectedProvider
    ): void {
        if (
            $order->provider
            !== $expectedProvider
        ) {
            throw new AuthApiException(
                409,
                'IDEMPOTENCY_KEY_CONFLICT',
                'This idempotency key has already been used for a different payment operation.'
            );
        }
    }
}
