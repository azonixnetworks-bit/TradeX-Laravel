<?php

namespace App\Services\Admin\TradingFloor;

use App\Models\TradingFloorClient;
use App\Models\TradingFloorCommissionInvoice;
use App\Models\TradingFloorCommissionPayment;
use Illuminate\Validation\ValidationException;

class TradingFloorCommissionService
{
    public function calculateAmount(TradingFloorClient $client, ?float $tradingProfit): float
    {
        $type = strtoupper((string) $client->commission_type);
        $rate = (float) ($client->commission_rate ?? 0);
        $fixed = (float) ($client->fixed_commission_amount ?? 0);

        if (in_array($type, ['PERCENTAGE', 'HYBRID'], true) && $tradingProfit === null) {
            throw ValidationException::withMessages([
                'trading_profit_amount' => 'Trading profit is required for percentage or hybrid commission.',
            ]);
        }

        if ($type === 'PERCENTAGE') {
            return round(max(0, (float) $tradingProfit) * $rate / 100, 2);
        }

        if ($type === 'FIXED') {
            return round(max(0, $fixed), 2);
        }

        if ($type === 'HYBRID') {
            return round(max(0, $fixed) + (max(0, (float) $tradingProfit) * $rate / 100), 2);
        }

        throw ValidationException::withMessages([
            'commission_type' => 'Unsupported trading floor commission type.',
        ]);
    }

    public function reconcileInvoice(TradingFloorCommissionInvoice $invoice): TradingFloorCommissionInvoice
    {
        $invoice->refresh();

        $paid = (float) TradingFloorCommissionPayment::query()
            ->where('trading_floor_commission_invoice_id', $invoice->id)
            ->where('status', TradingFloorCommissionPayment::STATUS_RECEIVED)
            ->sum('amount');

        $amount = (float) $invoice->commission_amount;
        $balance = max(0, round($amount - $paid, 2));

        if (in_array($invoice->status, [
            TradingFloorCommissionInvoice::STATUS_WAIVED,
            TradingFloorCommissionInvoice::STATUS_DISPUTED,
        ], true)) {
            $invoice->forceFill([
                'paid_amount' => round($paid, 2),
                'balance_amount' => $balance,
            ])->save();

            return $invoice->fresh();
        }

        if ($balance <= 0) {
            $status = TradingFloorCommissionInvoice::STATUS_PAID;
        } elseif ($invoice->due_date?->lt(now()->startOfDay())) {
            $status = TradingFloorCommissionInvoice::STATUS_OVERDUE;
        } elseif ($paid > 0) {
            $status = TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID;
        } else {
            $status = TradingFloorCommissionInvoice::STATUS_PENDING;
        }

        $invoice->forceFill([
            'paid_amount' => round($paid, 2),
            'balance_amount' => $balance,
            'status' => $status,
        ])->save();

        return $invoice->fresh();
    }

    public function markOverdueOpenInvoices(): int
    {
        return TradingFloorCommissionInvoice::query()
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('balance_amount', '>', 0)
            ->whereIn('status', [
                TradingFloorCommissionInvoice::STATUS_PENDING,
                TradingFloorCommissionInvoice::STATUS_PARTIALLY_PAID,
            ])
            ->update([
                'status' => TradingFloorCommissionInvoice::STATUS_OVERDUE,
                'updated_at' => now(),
            ]);
    }
}
