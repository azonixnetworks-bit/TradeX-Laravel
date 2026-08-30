<?php

namespace App\Services\Admin\Reporting;

use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\AffiliateMilestoneReward;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentOrder;
use App\Models\PayoutRequest;
use App\Services\Payments\PaymentMoney;
use App\Support\Reporting\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

final class AdminSalesReportService
{
    public function __construct(
        private readonly PaymentMoney $money,
        private readonly ReportDateRange $ranges
    ) {
    }

    public function report(
        ?string $from,
        ?string $to,
        ?string $provider,
        ?string $currency
    ): array {
        $prepared = $this->prepare(
            $from,
            $to,
            $provider,
            $currency
        );

        return [
            ...$prepared,
            'summary' => $this->rangeSummary(
                $prepared['range']['start'],
                $prepared['range']['end'],
                $prepared['provider'],
                $prepared['currency']
            ),
            'daily_breakdown' => $this->dailyBreakdown(
                $prepared['range']['start'],
                $prepared['range']['end'],
                $prepared['provider'],
                $prepared['currency']
            ),
            'money' => $this->money,
        ];
    }

    public function prepare(
        ?string $from,
        ?string $to,
        ?string $provider,
        ?string $currency
    ): array {
        $timezone = (string) config(
            'tradex_finance.timezone',
            'Asia/Colombo'
        );

        $range = $this->ranges->resolve(
            $from,
            $to,
            $timezone
        );

        $provider = $this->normalizeProvider($provider);
        $currency = $this->normalizeCurrency($currency);

        $maxPaymentId = (int) (Payment::query()->max('id') ?? 0);
        $maxAdjustmentId = (int) (PaymentAdjustment::query()->max('id') ?? 0);

        $paymentsCount = $this->paymentQuery(
            $range['start'],
            $range['end'],
            $provider,
            $currency
        )
            ->where('id', '<=', $maxPaymentId)
            ->count();

        $adjustmentsCount = $this->adjustmentQuery(
            $range['start'],
            $range['end'],
            $provider,
            $currency
        )
            ->where('id', '<=', $maxAdjustmentId)
            ->count();

        return [
            'range' => $range,
            'timezone' => $timezone,
            'provider' => $provider,
            'currency' => $currency,
            'max_payment_id' => $maxPaymentId,
            'max_adjustment_id' => $maxAdjustmentId,
            'payments_count' => $paymentsCount,
            'adjustments_count' => $adjustmentsCount,
            'row_count' => $paymentsCount + $adjustmentsCount,
        ];
    }

    public function paymentCursor(array $prepared): LazyCollection
    {
        return $this->paymentQuery(
            $prepared['range']['start'],
            $prepared['range']['end'],
            $prepared['provider'],
            $prepared['currency']
        )
            ->where('id', '<=', (int) $prepared['max_payment_id'])
            ->with([
                'order:id,uuid',
                'user:id,email',
            ])
            ->orderBy('id')
            ->lazyById(500);
    }

    public function adjustmentCursor(array $prepared): LazyCollection
    {
        return $this->adjustmentQuery(
            $prepared['range']['start'],
            $prepared['range']['end'],
            $prepared['provider'],
            $prepared['currency']
        )
            ->where('id', '<=', (int) $prepared['max_adjustment_id'])
            ->with('payment.order:id,uuid')
            ->orderBy('id')
            ->lazyById(500);
    }

    public function paymentRow(
        Payment $payment,
        string $timezone
    ): array {
        return [
            'PAYMENT',
            $payment->cleared_at?->timezone($timezone)->toDateString() ?? '',
            $payment->uuid,
            $payment->order?->uuid ?? '',
            $payment->user?->email ?? '',
            $payment->provider,
            $payment->currency,
            (int) $payment->gross_amount_minor,
            $payment->processor_fee_minor === null
                ? ''
                : (int) $payment->processor_fee_minor,
            '',
            $payment->status,
            $payment->cleared_at?->timezone($timezone)->toIso8601String() ?? '',
        ];
    }

    public function adjustmentRow(
        PaymentAdjustment $adjustment,
        string $timezone
    ): array {
        return [
            'ADJUSTMENT',
            $adjustment->created_at?->timezone($timezone)->toDateString() ?? '',
            $adjustment->uuid,
            $adjustment->payment?->order?->uuid ?? '',
            '',
            $adjustment->payment?->provider ?? '',
            $adjustment->currency,
            '',
            '',
            (int) $adjustment->amount_minor,
            $adjustment->type,
            $adjustment->created_at?->timezone($timezone)->toIso8601String() ?? '',
        ];
    }

    private function rangeSummary(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $provider,
        ?string $currency
    ): array {
        $payments = $this->paymentQuery(
            $start,
            $end,
            $provider,
            $currency
        )->get([
            'id',
            'provider',
            'currency',
            'gross_amount_minor',
            'processor_fee_minor',
            'status',
            'cleared_at',
        ]);

        $adjustments = $this->adjustmentQuery(
            $start,
            $end,
            $provider,
            $currency
        )->get([
            'id',
            'payment_id',
            'type',
            'amount_minor',
            'currency',
            'created_at',
        ]);

        $currencyBuckets = [];

        foreach ($payments as $payment) {
            $code = strtoupper((string) $payment->currency);
            $currencyBuckets[$code] ??= $this->emptyCurrency($code);

            $currencyBuckets[$code]['payments_count']++;
            $currencyBuckets[$code]['gross_minor'] +=
                (int) $payment->gross_amount_minor;

            if ($payment->processor_fee_minor !== null) {
                $currencyBuckets[$code]['known_fee_payments_count']++;
                $currencyBuckets[$code]['fees_minor'] +=
                    (int) $payment->processor_fee_minor;
            } else {
                $currencyBuckets[$code]['unknown_fee_payments_count']++;
            }
        }

        foreach ($adjustments as $adjustment) {
            $code = strtoupper((string) $adjustment->currency);
            $currencyBuckets[$code] ??= $this->emptyCurrency($code);

            $currencyBuckets[$code]['adjustments_count']++;
            $currencyBuckets[$code]['adjustments_minor'] +=
                (int) $adjustment->amount_minor;

            if (
                $adjustment->type
                === PaymentAdjustment::TYPE_PAYMENT_REVERSAL
            ) {
                $currencyBuckets[$code]['reversal_count']++;
            }
        }

        foreach ($currencyBuckets as &$bucket) {
            $bucket['net_cash_minor'] =
                (int) $bucket['gross_minor']
                + (int) $bucket['adjustments_minor'];

            $bucket['known_net_minor'] =
                (int) $bucket['net_cash_minor']
                - (int) $bucket['fees_minor'];
        }
        unset($bucket);

        ksort($currencyBuckets);

        return [
            'currencies' => $currencyBuckets,
            'affiliate' => $this->affiliateSummary($start, $end),
            'payments_count' => $payments->count(),
            'adjustments_count' => $adjustments->count(),
        ];
    }

    private function dailyBreakdown(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $provider,
        ?string $currency
    ): array {
        $rows = [];

        $payments = $this->paymentQuery(
            $start,
            $end,
            $provider,
            $currency
        )->get([
            'id',
            'currency',
            'gross_amount_minor',
            'processor_fee_minor',
            'cleared_at',
        ]);

        $adjustments = $this->adjustmentQuery(
            $start,
            $end,
            $provider,
            $currency
        )->get([
            'id',
            'currency',
            'amount_minor',
            'created_at',
        ]);

        foreach ($payments as $payment) {
            $date = $payment->cleared_at
                ->timezone($this->timezone())
                ->toDateString();

            $code = strtoupper((string) $payment->currency);
            $key = $date.'|'.$code;
            $rows[$key] ??= $this->emptyDailyRow($date, $code);

            $rows[$key]['payments_count']++;
            $rows[$key]['gross_minor'] +=
                (int) $payment->gross_amount_minor;
            $rows[$key]['fees_minor'] +=
                (int) ($payment->processor_fee_minor ?? 0);
        }

        foreach ($adjustments as $adjustment) {
            $date = $adjustment->created_at
                ->timezone($this->timezone())
                ->toDateString();

            $code = strtoupper((string) $adjustment->currency);
            $key = $date.'|'.$code;
            $rows[$key] ??= $this->emptyDailyRow($date, $code);

            $rows[$key]['adjustments_minor'] +=
                (int) $adjustment->amount_minor;
        }

        foreach ($rows as &$row) {
            $row['known_net_minor'] =
                (int) $row['gross_minor']
                + (int) $row['adjustments_minor']
                - (int) $row['fees_minor'];
        }
        unset($row);

        ksort($rows);

        return array_values($rows);
    }

    private function affiliateSummary(
        CarbonImmutable $start,
        CarbonImmutable $end
    ): array {
        $commissions = (int) AffiliateCommission::query()
            ->whereBetween('earned_at', [$start, $end])
            ->where('currency', 'USD')
            ->sum('commission_amount_cents');

        $commissionAdjustments = (int) AffiliateCommissionAdjustment::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('currency', 'USD')
            ->sum('amount_cents');

        $gifts = (int) AffiliateMilestoneReward::query()
            ->whereBetween('earned_at', [$start, $end])
            ->where('currency', 'USD')
            ->sum('gift_amount_cents');

        $paidPayouts = (int) PayoutRequest::query()
            ->where('status', PayoutRequest::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->where('currency', 'USD')
            ->sum('amount_cents');

        $netCommission = $commissions + $commissionAdjustments;

        return [
            'commission_earned_cents' => $commissions,
            'commission_adjustments_cents' => $commissionAdjustments,
            'net_commission_expense_cents' => $netCommission,
            'milestone_gifts_cents' => $gifts,
            'total_accrued_expense_cents' => $netCommission + $gifts,
            'cash_payouts_paid_cents' => $paidPayouts,
        ];
    }

    private function paymentQuery(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $provider,
        ?string $currency
    ): Builder {
        return Payment::query()
            ->whereBetween('cleared_at', [$start, $end])
            ->when(
                $provider !== null,
                fn (Builder $query) => $query->where('provider', $provider)
            )
            ->when(
                $currency !== null,
                fn (Builder $query) => $query->where('currency', $currency)
            );
    }

    private function adjustmentQuery(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $provider,
        ?string $currency
    ): Builder {
        return PaymentAdjustment::query()
            ->whereBetween('created_at', [$start, $end])
            ->when(
                $currency !== null,
                fn (Builder $query) => $query->where('currency', $currency)
            )
            ->when(
                $provider !== null,
                fn (Builder $query) => $query->whereHas(
                    'payment',
                    fn (Builder $payment) => $payment->where(
                        'provider',
                        $provider
                    )
                )
            );
    }

    private function emptyCurrency(string $currency): array
    {
        return [
            'currency' => strtoupper($currency),
            'payments_count' => 0,
            'gross_minor' => 0,
            'fees_minor' => 0,
            'known_fee_payments_count' => 0,
            'unknown_fee_payments_count' => 0,
            'adjustments_count' => 0,
            'reversal_count' => 0,
            'adjustments_minor' => 0,
            'net_cash_minor' => 0,
            'known_net_minor' => 0,
        ];
    }

    private function emptyDailyRow(
        string $date,
        string $currency
    ): array {
        return [
            'date' => $date,
            'currency' => strtoupper($currency),
            'payments_count' => 0,
            'gross_minor' => 0,
            'fees_minor' => 0,
            'adjustments_minor' => 0,
            'known_net_minor' => 0,
        ];
    }

    private function normalizeProvider(?string $provider): ?string
    {
        $provider = strtoupper(trim((string) $provider));

        return $provider === '' ? null : $provider;
    }

    private function normalizeCurrency(?string $currency): ?string
    {
        $currency = strtoupper(trim((string) $currency));

        return $currency === '' ? null : $currency;
    }

    private function timezone(): string
    {
        return (string) config(
            'tradex_finance.timezone',
            'Asia/Colombo'
        );
    }
}
