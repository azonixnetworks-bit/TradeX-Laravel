<?php

namespace App\Services\Payments;

use App\Models\BankDeposit;
use App\Models\CryptoDeposit;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\UsdtDeposit;

class PaymentApiPresenter
{
    public function __construct(
        private readonly PaymentMoney $money
    ) {
    }

    public function order(PaymentOrder $order): array
    {
        $order->loadMissing([
            'payment',
            'bankDeposit',
            'usdtDeposit',
            'cryptoDeposit',
        ]);

        return [
            'uuid' => $order->uuid,
            'product_code' => $order->product_code,
            'provider' => $order->provider,
            'country_code' => $order->country_code,
            'amount' => $this->money->present(
                (int) $order->amount_minor,
                $order->currency
            ),
            'status' => $order->status,
            'approval_url' =>
                in_array(
                    $order->provider,
                    [
                        PaymentOrder::PROVIDER_PAYPAL,
                        PaymentOrder::PROVIDER_STRIPE,
                        PaymentOrder::PROVIDER_NOWPAYMENTS,
                    ],
                    true
                )
                && $order->status
                    === PaymentOrder::STATUS_AWAITING_USER_ACTION
                    ? $order->approval_url
                    : null,
            'manual_payment' => $this->manualPayment($order),
            'manual_submission' =>
                $this->manualSubmission($order),
            'payment' =>
                $order->payment instanceof Payment
                    ? $this->payment($order->payment)
                    : null,
            'created_at' =>
                $order->created_at?->toISOString(),
            'cleared_at' =>
                $order->cleared_at?->toISOString(),
            'expires_at' =>
                $order->expires_at?->toISOString(),
        ];
    }

    public function payment(Payment $payment): array
    {
        return [
            'uuid' => $payment->uuid,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'gross' => $this->money->present(
                (int) $payment->gross_amount_minor,
                $payment->currency
            ),
            'processor_fee' =>
                $payment->processor_fee_minor !== null
                    ? $this->money->present(
                        (int) $payment->processor_fee_minor,
                        $payment->currency
                    )
                    : null,
            'net_eligible_revenue' =>
                $payment->net_eligible_revenue_minor !== null
                    ? $this->money->present(
                        (int) $payment->net_eligible_revenue_minor,
                        $payment->currency
                    )
                    : null,
            'affiliate_processing_status' =>
                $payment->affiliate_processing_status,
            'cleared_at' =>
                $payment->cleared_at?->toISOString(),
            'payment_reversed_at' =>
                $payment->payment_reversed_at?->toISOString(),
            'payment_reversal_code' =>
                $payment->payment_reversal_code,
        ];
    }

    private function manualPayment(
        PaymentOrder $order
    ): ?array {
        if (
            blank($order->manual_reference)
            || blank($order->receiving_account_snapshot)
        ) {
            return null;
        }

        $snapshot = (array) $order->receiving_account_snapshot;

        $data = [
            'reference' => $order->manual_reference,
            'receiving_account_uuid' =>
                $snapshot['account_uuid'] ?? null,
            'type' => $snapshot['type'] ?? null,
            'display_name' =>
                $snapshot['display_name'] ?? null,
            'amount' => [
                'currency' =>
                    (string) (
                        $snapshot['currency']
                        ?? $order->currency
                    ),
                'amount_minor' =>
                    (int) (
                        $snapshot['amount_minor']
                        ?? $order->amount_minor
                    ),
                'scale' =>
                    (int) (
                        $snapshot['amount_scale']
                        ?? (
                            strtoupper(
                                (string) (
                                    $snapshot['currency']
                                    ?? $order->currency
                                )
                            ) === 'USDT'
                                ? 6
                                : 2
                        )
                    ),
                'amount' =>
                    (string) (
                        $snapshot['amount']
                        ?? $this->money->present(
                            (int) (
                                $snapshot['amount_minor']
                                ?? $order->amount_minor
                            ),
                            (string) (
                                $snapshot['currency']
                                ?? $order->currency
                            )
                        )['amount']
                    ),
            ],
            'instructions' =>
                $snapshot['instructions'] ?? null,
        ];

        if (($snapshot['type'] ?? null) === 'BANK') {
            $data += [
                'bank_name' =>
                    $snapshot['bank_name'] ?? null,
                'branch' =>
                    $snapshot['branch'] ?? null,
                'account_holder' =>
                    $snapshot['account_holder'] ?? null,
                'account_number' =>
                    $snapshot['account_number'] ?? null,
                'evidence_endpoint' =>
                    'POST /api/v1/payments/manual-orders/'
                    .$order->uuid
                    .'/bank-evidence',
            ];
        } else {
            $data += [
                'coin_name' =>
                    $snapshot['coin_name'] ?? null,
                'coin_symbol' =>
                    $snapshot['coin_symbol'] ?? null,
                'network' =>
                    $snapshot['network'] ?? null,
                'wallet_address' =>
                    $snapshot['wallet_address'] ?? null,
                'qr_available' =>
                    filled($snapshot['qr_path'] ?? null),
                'qr_endpoint' =>
                    filled($snapshot['qr_path'] ?? null)
                        ? 'GET /api/v1/payments/manual-orders/'
                            .$order->uuid
                            .'/qr'
                        : null,
                'evidence_endpoint' =>
                    'POST /api/v1/payments/manual-orders/'
                    .$order->uuid
                    .(
                        $order->provider
                            === PaymentOrder::PROVIDER_CRYPTO_MANUAL
                                ? '/crypto-evidence'
                                : '/usdt-evidence'
                    ),
            ];
        }

        return $data;
    }

    private function manualSubmission(
        PaymentOrder $order
    ): ?array {
        if ($order->bankDeposit instanceof BankDeposit) {
            return [
                'type' => 'LOCAL_BANK',
                'uuid' => $order->bankDeposit->uuid,
                'status' => $order->bankDeposit->status,
                'deposit_date' =>
                    $order->bankDeposit
                        ->deposit_date?->toDateString(),
                'proof_received' =>
                    filled($order->bankDeposit->proof_path),
                'submitted_at' =>
                    $order->bankDeposit
                        ->submitted_at?->toISOString(),
            ];
        }

        if ($order->usdtDeposit instanceof UsdtDeposit) {
            return [
                'type' => 'USDT_TRC20',
                'uuid' => $order->usdtDeposit->uuid,
                'status' => $order->usdtDeposit->status,
                'network' => $order->usdtDeposit->network,
                'tx_hash' => $order->usdtDeposit->tx_hash,
                'proof_received' =>
                    filled($order->usdtDeposit->proof_path),
                'submitted_at' =>
                    $order->usdtDeposit
                        ->submitted_at?->toISOString(),
            ];
        }

        if (
            $order->cryptoDeposit
            instanceof CryptoDeposit
        ) {
            return [
                'type' =>
                    'CRYPTO_MANUAL',
                'uuid' =>
                    $order->cryptoDeposit
                        ->uuid,
                'status' =>
                    $order->cryptoDeposit
                        ->status,
                'coin_symbol' =>
                    $order->cryptoDeposit
                        ->coin_symbol,
                'network' =>
                    $order->cryptoDeposit
                        ->network,
                'tx_hash' =>
                    $order->cryptoDeposit
                        ->tx_hash,
                'amount' =>
                    $order->cryptoDeposit
                        ->amount_decimal,
                'proof_received' =>
                    filled(
                        $order->cryptoDeposit
                            ->proof_path
                    ),
                'submitted_at' =>
                    $order->cryptoDeposit
                        ->submitted_at
                        ?->toISOString(),
            ];
        }

        return null;
    }
}
