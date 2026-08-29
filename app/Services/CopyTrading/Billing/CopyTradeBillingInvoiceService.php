<?php

namespace App\Services\CopyTrading\Billing;

use App\Models\AuditLog;
use App\Models\CopyTradeBillingCycle;
use App\Models\CopyTradeBillingInvoice;
use App\Models\CopyTradeBillingInvoiceLine;
use App\Models\CopyTradeBillingSnapshot;
use App\Models\CopyTradeBrokerAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CopyTradeBillingInvoiceService
{
    public function issue(
        CopyTradeBrokerAccount $account,
        CopyTradeBillingCycle $cycle,
        CopyTradeBillingSnapshot $snapshot,
        string $invoiceType,
        string $productCode,
        int $amountMinor,
        string $lineType,
        string $description,
        array $lineMetadata = [],
        ?CarbonInterface $issuedAt = null
    ): CopyTradeBillingInvoice {
        $key = hash(
            'sha256',
            implode('|', [
                $account->getKey(),
                $cycle->getKey(),
                $snapshot->uuid,
                $invoiceType,
                $productCode,
            ])
        );

        $existing = CopyTradeBillingInvoice::query()
            ->where('idempotency_key_hash', $key)
            ->first();

        if ($existing instanceof CopyTradeBillingInvoice) {
            return $existing->loadMissing([
                'lines',
                'snapshot',
                'cycle',
            ]);
        }

        return DB::transaction(
            function () use (
                $account,
                $cycle,
                $snapshot,
                $invoiceType,
                $productCode,
                $amountMinor,
                $lineType,
                $description,
                $lineMetadata,
                $key,
                $issuedAt
            ): CopyTradeBillingInvoice {
                $invoice = CopyTradeBillingInvoice::query()
                    ->create([
                        'idempotency_key_hash' => $key,
                        'user_id' => $account->user_id,
                        'broker_account_id' =>
                            $account->getKey(),
                        'billing_cycle_id' =>
                            $cycle->getKey(),
                        'billing_snapshot_id' =>
                            $snapshot->getKey(),
                        'invoice_type' => $invoiceType,
                        'product_code' => $productCode,
                        'currency' => 'USD',
                        'subtotal_minor' => $amountMinor,
                        'total_minor' => $amountMinor,
                        'status' =>
                            CopyTradeBillingInvoice::STATUS_OPEN,
                        'period_start' =>
                            $cycle->period_start,
                        'period_end' =>
                            $cycle->period_end,
                        'issued_at' =>
                            $issuedAt ?? now(),
                        'due_at' =>
                            $issuedAt ?? now(),
                    ]);

                CopyTradeBillingInvoiceLine::query()
                    ->create([
                        'billing_invoice_id' =>
                            $invoice->getKey(),
                        'line_type' => $lineType,
                        'description' => $description,
                        'amount_minor' => $amountMinor,
                        'metadata' => $lineMetadata,
                    ]);

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action:
                        'COPY_TRADE_BILLING_INVOICE_ISSUED',
                    module: 'COPY_TRADING',
                    targetType:
                        'COPY_TRADE_BILLING_INVOICE',
                    targetId: $invoice->getKey(),
                    newValues: [
                        'invoice_uuid' =>
                            $invoice->uuid,
                        'invoice_number' =>
                            $invoice->invoice_number,
                        'account_masked' =>
                            $account->maskedAccountNumber(),
                        'invoice_type' =>
                            $invoice->invoice_type,
                        'product_code' =>
                            $invoice->product_code,
                        'currency' =>
                            $invoice->currency,
                        'total_minor' =>
                            $invoice->total_minor,
                        'snapshot_uuid' =>
                            $snapshot->uuid,
                    ]
                );

                return $invoice->fresh([
                    'lines',
                    'snapshot',
                    'cycle',
                ]);
            },
            3
        );
    }
}
