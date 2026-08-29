<?php

namespace App\Services\CopyTrading\Billing;

use App\Models\CopyTradeBillingCycle;
use App\Models\CopyTradeBillingInvoice;
use App\Models\CopyTradeBrokerAccount;

class CopyTradeBillingPresenter
{
    public function account(
        CopyTradeBrokerAccount $account
    ): array {
        $account->loadMissing([
            'broker',
            'brokerMarket',
            'billingCycles',
            'billingInvoices.lines',
            'billingInvoices.snapshot',
        ]);

        $cycle = $account->billingCycles
            ->sortByDesc('period_end')
            ->first();

        return [
            'broker_account_uuid' =>
                $account->uuid,
            'account_number_masked' =>
                $account->maskedAccountNumber(),
            'broker_name' =>
                $account->broker?->name,
            'market_category' =>
                $account->brokerMarket?->market_category,
            'account_status' =>
                $account->status,
            'current_cycle' =>
                $cycle instanceof CopyTradeBillingCycle
                    ? $this->cycle($cycle)
                    : null,
            'invoices' =>
                $account->billingInvoices
                    ->sortByDesc('issued_at')
                    ->map(
                        fn (
                            CopyTradeBillingInvoice $invoice
                        ): array =>
                            $this->invoice($invoice)
                    )
                    ->values()
                    ->all(),
        ];
    }

    public function cycle(
        CopyTradeBillingCycle $cycle
    ): array {
        return [
            'uuid' => $cycle->uuid,
            'status' => $cycle->status,
            'cycle_basis' => $cycle->cycle_basis,
            'currency' => $cycle->currency,
            'active_tier_code' =>
                $cycle->active_tier_code,
            'active_full_fee_minor' =>
                (int) $cycle->active_full_fee_minor,
            'period_start' =>
                $cycle->period_start?->toIso8601String(),
            'period_end' =>
                $cycle->period_end?->toIso8601String(),
            'grace_expires_at' =>
                $cycle->grace_expires_at?->toIso8601String(),
        ];
    }

    public function invoice(
        CopyTradeBillingInvoice $invoice
    ): array {
        $invoice->loadMissing([
            'lines',
            'snapshot',
        ]);

        return [
            'uuid' => $invoice->uuid,
            'invoice_number' =>
                $invoice->invoice_number,
            'invoice_type' =>
                $invoice->invoice_type,
            'product_code' =>
                $invoice->product_code,
            'currency' =>
                $invoice->currency,
            'subtotal_minor' =>
                (int) $invoice->subtotal_minor,
            'total_minor' =>
                (int) $invoice->total_minor,
            'status' =>
                $invoice->status,
            'period_start' =>
                $invoice->period_start?->toIso8601String(),
            'period_end' =>
                $invoice->period_end?->toIso8601String(),
            'issued_at' =>
                $invoice->issued_at?->toIso8601String(),
            'due_at' =>
                $invoice->due_at?->toIso8601String(),
            'paid_at' =>
                $invoice->paid_at?->toIso8601String(),
            'snapshot' =>
                $invoice->snapshot
                    ? [
                        'verified_billable_balance_minor' =>
                            (int) $invoice->snapshot
                                ->verified_billable_balance_minor,
                        'billing_eligible_balance_minor' =>
                            (int) $invoice->snapshot
                                ->billing_eligible_balance_minor,
                        'tier_code' =>
                            $invoice->snapshot
                                ->tier_code,
                        'full_monthly_fee_minor' =>
                            (int) $invoice->snapshot
                                ->full_monthly_fee_minor,
                        'formula_code' =>
                            $invoice->snapshot
                                ->formula_code,
                        'ratio_numerator' =>
                            (int) $invoice->snapshot
                                ->ratio_numerator,
                        'ratio_denominator' =>
                            (int) $invoice->snapshot
                                ->ratio_denominator,
                    ]
                    : null,
            'lines' =>
                $invoice->lines
                    ->map(
                        fn ($line): array => [
                            'line_type' =>
                                $line->line_type,
                            'description' =>
                                $line->description,
                            'amount_minor' =>
                                (int) $line->amount_minor,
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }
}
