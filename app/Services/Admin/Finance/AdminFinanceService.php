<?php

namespace App\Services\Admin\Finance;

use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAdjustment;
use App\Models\AffiliateMilestoneReward;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentOrder;
use App\Models\PayoutRequest;
use App\Services\Payments\PaymentMoney;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminFinanceService
{
    public function __construct(
        private readonly PaymentMoney $money
    ) {
    }

    public function dashboard(
        ?string $month = null
    ): array {
        $month = $this->normalizeMonth($month);

        [$monthStart, $monthEnd] = $this->monthRange($month);
        [$todayStart, $todayEnd] = $this->dateRange(
            $this->localNow()->toDateString()
        );

        $monthly = $this->rangeSummary(
            $monthStart,
            $monthEnd
        );

        $today = $this->rangeSummary(
            $todayStart,
            $todayEnd
        );

        $usd = $monthly['currencies']['USD'] ?? $this->emptyCurrency('USD');

        $knownUsdContribution =
            (int) $usd['known_net_minor']
            - (int) $monthly['affiliate']['total_accrued_expense_cents'];

        return [
            'month' => $month,
            'timezone' => $this->timezone(),
            'monthly' => $monthly,
            'today' => $today,
            'known_usd_contribution_cents' => $knownUsdContribution,
            'pending_manual_reviews' => PaymentOrder::query()
                ->where('status', PaymentOrder::STATUS_PENDING_REVIEW)
                ->whereIn('provider', [
                    PaymentOrder::PROVIDER_LOCAL_BANK,
                    PaymentOrder::PROVIDER_USDT_TRC20,
                ])
                ->count(),
            'active_payout_reserved_cents' => (int) PayoutRequest::query()
                ->whereIn('status', PayoutRequest::ACTIVE_STATUSES)
                ->where('currency', 'USD')
                ->sum('amount_cents'),
            'reconciliation_issue_count' => $this->reconciliation()['issue_count'],
            'money' => $this->money,
        ];
    }

    public function dailySales(
        ?string $date = null,
        ?string $provider = null,
        ?string $currency = null,
        int $perPage = 20
    ): array {
        $date = $this->normalizeDate($date);
        $provider = $this->normalizeProvider($provider);
        $currency = $this->normalizeCurrency($currency);
        [$start, $end] = $this->dateRange($date);

        $maxPerPage = max(
            1,
            (int) config('tradex_finance.pagination.max_per_page', 100)
        );

        $perPage = min(
            $maxPerPage,
            max(1, $perPage)
        );

        $paymentsQuery = $this->paymentQuery(
            $start,
            $end,
            $provider,
            $currency
        )
            ->with(['order', 'user'])
            ->orderByDesc('cleared_at')
            ->orderByDesc('id');

        /** @var LengthAwarePaginator $payments */
        $payments = $paymentsQuery
            ->paginate(
                $perPage,
                ['*'],
                'payments_page'
            )
            ->withQueryString();

        $adjustmentsQuery = $this->adjustmentQuery(
            $start,
            $end,
            $provider,
            $currency
        )
            ->with('payment.order')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        /** @var LengthAwarePaginator $adjustments */
        $adjustments = $adjustmentsQuery
            ->paginate(
                $perPage,
                ['*'],
                'adjustments_page'
            )
            ->withQueryString();

        return [
            'date' => $date,
            'timezone' => $this->timezone(),
            'provider' => $provider,
            'currency' => $currency,
            'summary' => $this->rangeSummary(
                $start,
                $end,
                $provider,
                $currency
            ),
            'payments' => $payments,
            'adjustments' => $adjustments,
            'money' => $this->money,
        ];
    }

    public function dailySalesExport(
        ?string $date = null,
        ?string $provider = null,
        ?string $currency = null
    ): array {
        $date = $this->normalizeDate($date);
        $provider = $this->normalizeProvider($provider);
        $currency = $this->normalizeCurrency($currency);
        [$start, $end] = $this->dateRange($date);

        $rows = [];

        $payments = $this->paymentQuery(
            $start,
            $end,
            $provider,
            $currency
        )
            ->with(['order', 'user'])
            ->orderBy('cleared_at')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $rows[] = [
                'PAYMENT',
                $date,
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
                $payment->cleared_at?->timezone($this->timezone())->toIso8601String() ?? '',
            ];
        }

        $adjustments = $this->adjustmentQuery(
            $start,
            $end,
            $provider,
            $currency
        )
            ->with('payment.order')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($adjustments as $adjustment) {
            $rows[] = [
                'ADJUSTMENT',
                $date,
                $adjustment->uuid,
                $adjustment->payment?->order?->uuid ?? '',
                '',
                $adjustment->payment?->provider ?? '',
                $adjustment->currency,
                '',
                '',
                (int) $adjustment->amount_minor,
                $adjustment->type,
                $adjustment->created_at?->timezone($this->timezone())->toIso8601String() ?? '',
            ];
        }

        return [
            'date' => $date,
            'rows' => $rows,
        ];
    }

    public function monthlySales(
        ?string $month = null,
        ?string $provider = null,
        ?string $currency = null
    ): array {
        $month = $this->normalizeMonth($month);
        $provider = $this->normalizeProvider($provider);
        $currency = $this->normalizeCurrency($currency);
        [$start, $end] = $this->monthRange($month);

        return [
            'month' => $month,
            'timezone' => $this->timezone(),
            'provider' => $provider,
            'currency' => $currency,
            'summary' => $this->rangeSummary(
                $start,
                $end,
                $provider,
                $currency
            ),
            'daily_breakdown' => $this->monthlyDailyBreakdown(
                $start,
                $end,
                $provider,
                $currency
            ),
            'money' => $this->money,
        ];
    }

    public function monthlySalesExport(
        ?string $month = null,
        ?string $provider = null,
        ?string $currency = null
    ): array {
        $month = $this->normalizeMonth($month);
        [$start, $end] = $this->monthRange($month);

        $rows = collect(
            $this->monthlyDailyBreakdown(
                $start,
                $end,
                $this->normalizeProvider($provider),
                $this->normalizeCurrency($currency)
            )
        )->map(
            fn (array $row): array => [
                $row['date'],
                $row['currency'],
                $row['payments_count'],
                $row['gross_minor'],
                $row['fees_minor'],
                $row['adjustments_minor'],
                $row['known_net_minor'],
            ]
        )->values()->all();

        return [
            'month' => $month,
            'rows' => $rows,
        ];
    }

    public function dailyReport(
        ?string $date = null
    ): array {
        $date = $this->normalizeDate($date);
        [$start, $end] = $this->dateRange($date);

        return [
            'date' => $date,
            'timezone' => $this->timezone(),
            'summary' => $this->rangeSummary(
                $start,
                $end
            ),
            'pending_manual_reviews' => PaymentOrder::query()
                ->where('status', PaymentOrder::STATUS_PENDING_REVIEW)
                ->whereIn('provider', [
                    PaymentOrder::PROVIDER_LOCAL_BANK,
                    PaymentOrder::PROVIDER_USDT_TRC20,
                ])
                ->count(),
            'active_payout_reserved_cents' => (int) PayoutRequest::query()
                ->whereIn('status', PayoutRequest::ACTIVE_STATUSES)
                ->where('currency', 'USD')
                ->sum('amount_cents'),
        ];
    }

    public function previousLocalDate(): string
    {
        return $this->localNow()
            ->subDay()
            ->toDateString();
    }

    public function reconciliation(): array
    {
        $limit = max(
            10,
            (int) config(
                'tradex_finance.reconciliation_scan_limit',
                500
            )
        );

        $issues = [];

        $missingPayments = PaymentOrder::query()
            ->whereIn('status', [
                PaymentOrder::STATUS_CLEARED,
                PaymentOrder::STATUS_PAYMENT_REVERSED,
            ])
            ->whereDoesntHave('payment')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($missingPayments as $order) {
            $issues[] = $this->issue(
                'PAYMENT',
                $order->id,
                $order->uuid,
                'CLEARED_ORDER_MISSING_PAYMENT',
                'A cleared/reversed payment order has no payment ledger row.'
            );
        }

        $mismatchedPayments = Payment::query()
            ->join(
                'payment_orders as finance_orders',
                'finance_orders.id',
                '=',
                'payments.payment_order_id'
            )
            ->where(function ($query): void {
                $query
                    ->whereColumn(
                        'payments.user_id',
                        '!=',
                        'finance_orders.user_id'
                    )
                    ->orWhereColumn(
                        'payments.provider',
                        '!=',
                        'finance_orders.provider'
                    )
                    ->orWhereColumn(
                        'payments.currency',
                        '!=',
                        'finance_orders.currency'
                    )
                    ->orWhereColumn(
                        'payments.gross_amount_minor',
                        '!=',
                        'finance_orders.amount_minor'
                    )
                    ->orWhere(function ($status): void {
                        $status
                            ->where(
                                'payments.status',
                                Payment::STATUS_CLEARED
                            )
                            ->where(
                                'finance_orders.status',
                                '!=',
                                PaymentOrder::STATUS_CLEARED
                            );
                    })
                    ->orWhere(function ($status): void {
                        $status
                            ->where(
                                'payments.status',
                                Payment::STATUS_PAYMENT_REVERSED
                            )
                            ->where(
                                'finance_orders.status',
                                '!=',
                                PaymentOrder::STATUS_PAYMENT_REVERSED
                            );
                    });
            })
            ->select('payments.*')
            ->orderByDesc('payments.id')
            ->limit($limit)
            ->get();

        foreach ($mismatchedPayments as $payment) {
            $issues[] = $this->issue(
                'PAYMENT',
                $payment->id,
                $payment->uuid,
                'PAYMENT_ORDER_CONTRACT_MISMATCH',
                'Payment user/provider/currency/amount/status does not match its order.'
            );
        }

        $reversedWithoutAdjustment = Payment::query()
            ->where(
                'status',
                Payment::STATUS_PAYMENT_REVERSED
            )
            ->whereDoesntHave(
                'adjustments',
                fn (Builder $query) => $query->where(
                    'type',
                    PaymentAdjustment::TYPE_PAYMENT_REVERSAL
                )
            )
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($reversedWithoutAdjustment as $payment) {
            $issues[] = $this->issue(
                'PAYMENT',
                $payment->id,
                $payment->uuid,
                'REVERSED_PAYMENT_MISSING_ADJUSTMENT',
                'A reversed payment is missing its signed payment-adjustment ledger row.'
            );
        }

        $adjustmentMismatches = PaymentAdjustment::query()
            ->join(
                'payments as finance_payments',
                'finance_payments.id',
                '=',
                'payment_adjustments.payment_id'
            )
            ->whereColumn(
                'payment_adjustments.currency',
                '!=',
                'finance_payments.currency'
            )
            ->select('payment_adjustments.*')
            ->orderByDesc('payment_adjustments.id')
            ->limit($limit)
            ->get();

        foreach ($adjustmentMismatches as $adjustment) {
            $issues[] = $this->issue(
                'PAYMENT_ADJUSTMENT',
                $adjustment->id,
                $adjustment->uuid,
                'ADJUSTMENT_CURRENCY_MISMATCH',
                'A payment adjustment currency does not match its payment.'
            );
        }

        $payouts = PayoutRequest::query()
            ->whereIn('status', [
                ...PayoutRequest::ACTIVE_STATUSES,
                PayoutRequest::STATUS_PAID,
            ])
            ->withSum('allocations', 'amount_cents')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($payouts as $payout) {
            if (
                (int) ($payout->allocations_sum_amount_cents ?? 0)
                !== (int) $payout->amount_cents
            ) {
                $issues[] = $this->issue(
                    'PAYOUT',
                    $payout->id,
                    $payout->uuid,
                    'PAYOUT_ALLOCATION_MISMATCH',
                    'Payout allocation total does not equal the payout amount.'
                );
            }

            if (
                in_array(
                    $payout->status,
                    [
                        PayoutRequest::STATUS_APPROVED,
                        PayoutRequest::STATUS_PAID,
                    ],
                    true
                )
                && $payout->reviewed_by_admin_id === null
            ) {
                $issues[] = $this->issue(
                    'PAYOUT',
                    $payout->id,
                    $payout->uuid,
                    'PAYOUT_REVIEWER_MISSING',
                    'Approved/paid payout has no recorded reviewing admin.'
                );
            }

            if (
                $payout->status === PayoutRequest::STATUS_PAID
                && blank($payout->provider_reference)
            ) {
                $issues[] = $this->issue(
                    'PAYOUT',
                    $payout->id,
                    $payout->uuid,
                    'PAID_PAYOUT_REFERENCE_MISSING',
                    'Paid payout has no external settlement reference.'
                );
            }
        }

        return [
            'issues' => $issues,
            'issue_count' => count($issues),
            'scan_limit' => $limit,
            'pending_manual_reviews' => PaymentOrder::query()
                ->where('status', PaymentOrder::STATUS_PENDING_REVIEW)
                ->whereIn('provider', [
                    PaymentOrder::PROVIDER_LOCAL_BANK,
                    PaymentOrder::PROVIDER_USDT_TRC20,
                ])
                ->count(),
            'active_payouts' => PayoutRequest::query()
                ->whereIn('status', PayoutRequest::ACTIVE_STATUSES)
                ->count(),
        ];
    }

    private function rangeSummary(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $provider = null,
        ?string $currency = null
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
            $code = strtoupper(
                (string) $payment->currency
            );

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
            $code = strtoupper(
                (string) $adjustment->currency
            );

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

        $providerBuckets = [];

        foreach ($payments as $payment) {
            $key = strtoupper((string) $payment->provider)
                .'|'
                .strtoupper((string) $payment->currency);

            $providerBuckets[$key] ??= [
                'provider' => strtoupper((string) $payment->provider),
                'currency' => strtoupper((string) $payment->currency),
                'payments_count' => 0,
                'gross_minor' => 0,
                'fees_minor' => 0,
            ];

            $providerBuckets[$key]['payments_count']++;
            $providerBuckets[$key]['gross_minor'] +=
                (int) $payment->gross_amount_minor;
            $providerBuckets[$key]['fees_minor'] +=
                (int) ($payment->processor_fee_minor ?? 0);
        }

        ksort($providerBuckets);

        $affiliate = $this->affiliateSummary(
            $start,
            $end
        );

        return [
            'currencies' => $currencyBuckets,
            'providers' => array_values($providerBuckets),
            'affiliate' => $affiliate,
            'payments_count' => $payments->count(),
            'adjustments_count' => $adjustments->count(),
        ];
    }

    private function affiliateSummary(
        CarbonImmutable $start,
        CarbonImmutable $end
    ): array {
        $commissions = (int) AffiliateCommission::query()
            ->whereBetween('earned_at', [$start, $end])
            ->where('currency', 'USD')
            ->sum('commission_amount_cents');

        $commissionAdjustments =
            (int) AffiliateCommissionAdjustment::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('currency', 'USD')
                ->sum('amount_cents');

        $gifts = (int) AffiliateMilestoneReward::query()
            ->whereBetween('earned_at', [$start, $end])
            ->where('currency', 'USD')
            ->sum('gift_amount_cents');

        $paidPayouts = (int) PayoutRequest::query()
            ->where(
                'status',
                PayoutRequest::STATUS_PAID
            )
            ->whereBetween('paid_at', [$start, $end])
            ->where('currency', 'USD')
            ->sum('amount_cents');

        $netCommission =
            $commissions
            + $commissionAdjustments;

        return [
            'commission_earned_cents' => $commissions,
            'commission_adjustments_cents' => $commissionAdjustments,
            'net_commission_expense_cents' => $netCommission,
            'milestone_gifts_cents' => $gifts,
            'total_accrued_expense_cents' =>
                $netCommission + $gifts,
            'cash_payouts_paid_cents' => $paidPayouts,
        ];
    }

    private function monthlyDailyBreakdown(
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
            'currency',
            'amount_minor',
            'created_at',
        ]);

        $rows = [];

        foreach ($payments as $payment) {
            $date = $payment->cleared_at
                ->timezone($this->timezone())
                ->toDateString();

            $code = strtoupper(
                (string) $payment->currency
            );

            $key = $date.'|'.$code;

            $rows[$key] ??= $this->emptyDailyRow(
                $date,
                $code
            );

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

            $code = strtoupper(
                (string) $adjustment->currency
            );

            $key = $date.'|'.$code;

            $rows[$key] ??= $this->emptyDailyRow(
                $date,
                $code
            );

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

    private function paymentQuery(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $provider,
        ?string $currency
    ): Builder {
        return Payment::query()
            ->whereBetween(
                'cleared_at',
                [$start, $end]
            )
            ->when(
                $provider !== null,
                fn (Builder $query) => $query->where(
                    'provider',
                    $provider
                )
            )
            ->when(
                $currency !== null,
                fn (Builder $query) => $query->where(
                    'currency',
                    $currency
                )
            );
    }

    private function adjustmentQuery(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $provider,
        ?string $currency
    ): Builder {
        return PaymentAdjustment::query()
            ->whereBetween(
                'created_at',
                [$start, $end]
            )
            ->when(
                $currency !== null,
                fn (Builder $query) => $query->where(
                    'currency',
                    $currency
                )
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

    private function emptyCurrency(
        string $currency
    ): array {
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

    private function issue(
        string $domain,
        int $id,
        ?string $uuid,
        string $code,
        string $message
    ): array {
        return [
            'domain' => $domain,
            'id' => $id,
            'uuid' => $uuid,
            'code' => $code,
            'message' => $message,
        ];
    }

    private function normalizeProvider(
        ?string $provider
    ): ?string {
        if ($provider === null || trim($provider) === '') {
            return null;
        }

        return strtoupper(trim($provider));
    }

    private function normalizeCurrency(
        ?string $currency
    ): ?string {
        if ($currency === null || trim($currency) === '') {
            return null;
        }

        return strtoupper(trim($currency));
    }

    private function normalizeDate(
        ?string $date
    ): string {
        if ($date === null || trim($date) === '') {
            return $this->localNow()->toDateString();
        }

        return trim($date);
    }

    private function normalizeMonth(
        ?string $month
    ): string {
        if ($month === null || trim($month) === '') {
            return $this->localNow()->format('Y-m');
        }

        return trim($month);
    }

    private function dateRange(
        string $date
    ): array {
        $local = CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date.' 00:00:00',
            $this->timezone()
        );

        return [
            $local
                ->startOfDay()
                ->timezone($this->databaseTimezone()),
            $local
                ->endOfDay()
                ->timezone($this->databaseTimezone()),
        ];
    }

    private function monthRange(
        string $month
    ): array {
        $local = CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $month.'-01 00:00:00',
            $this->timezone()
        );

        return [
            $local
                ->startOfMonth()
                ->timezone($this->databaseTimezone()),
            $local
                ->endOfMonth()
                ->timezone($this->databaseTimezone()),
        ];
    }

    private function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now(
            $this->timezone()
        );
    }

    private function timezone(): string
    {
        return (string) config(
            'tradex_finance.timezone',
            'Asia/Colombo'
        );
    }

    private function databaseTimezone(): string
    {
        return (string) config(
            'app.timezone',
            'UTC'
        );
    }
}
