<?php

namespace App\Services\Payments;

use App\Models\BankDeposit;
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
        $order->loadMissing(['payment', 'bankDeposit', 'usdtDeposit']);

        return [
            'uuid' => $order->uuid,
            'product_code' => $order->product_code,
            'provider' => $order->provider,
            'country_code' => $order->country_code,
            'amount' => $this->money->present((int) $order->amount_minor, $order->currency),
            'status' => $order->status,
            'approval_url' => $this->approvalUrl($order),
            'manual_submission' => $this->manualSubmission($order),
            'payment' => $order->payment instanceof Payment
                ? $this->payment($order->payment)
                : null,
            'created_at' => $order->created_at?->toISOString(),
            'cleared_at' => $order->cleared_at?->toISOString(),
            'expires_at' => $order->expires_at?->toISOString(),
        ];
    }

    public function payment(Payment $payment): array
    {
        return [
            'uuid' => $payment->uuid,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'gross' => $this->money->present((int) $payment->gross_amount_minor, $payment->currency),
            'processor_fee' => $payment->processor_fee_minor !== null
                ? $this->money->present((int) $payment->processor_fee_minor, $payment->currency)
                : null,
            'net_eligible_revenue' => $payment->net_eligible_revenue_minor !== null
                ? $this->money->present((int) $payment->net_eligible_revenue_minor, $payment->currency)
                : null,
            'affiliate_processing_status' => $payment->affiliate_processing_status,
            'cleared_at' => $payment->cleared_at?->toISOString(),
            'payment_reversed_at' => $payment->payment_reversed_at?->toISOString(),
            'payment_reversal_code' => $payment->payment_reversal_code,
        ];
    }

    private function approvalUrl(PaymentOrder $order): ?string
    {
        if (
            $order->status !== PaymentOrder::STATUS_AWAITING_USER_ACTION
            || ! in_array($order->provider, [
                PaymentOrder::PROVIDER_PAYPAL,
                PaymentOrder::PROVIDER_STRIPE,
                PaymentOrder::PROVIDER_NOWPAYMENTS,
            ], true)
        ) {
            return null;
        }

        return $order->approval_url;
    }

    private function manualSubmission(PaymentOrder $order): ?array
    {
        if ($order->bankDeposit instanceof BankDeposit) {
            return [
                'type' => 'LOCAL_BANK',
                'uuid' => $order->bankDeposit->uuid,
                'status' => $order->bankDeposit->status,
                'deposit_date' => $order->bankDeposit->deposit_date?->toDateString(),
                'proof_received' => filled($order->bankDeposit->proof_path),
                'submitted_at' => $order->bankDeposit->submitted_at?->toISOString(),
            ];
        }

        if ($order->usdtDeposit instanceof UsdtDeposit) {
            return [
                'type' => 'USDT_TRC20',
                'uuid' => $order->usdtDeposit->uuid,
                'status' => $order->usdtDeposit->status,
                'network' => $order->usdtDeposit->network,
                'tx_hash' => $order->usdtDeposit->tx_hash,
                'submitted_at' => $order->usdtDeposit->submitted_at?->toISOString(),
            ];
        }

        return null;
    }
}
