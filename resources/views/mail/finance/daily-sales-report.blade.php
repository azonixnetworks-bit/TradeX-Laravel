<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TradeX Daily Sales Report</title>
</head>
<body style="font-family:Arial,sans-serif;color:#171717;">
    <h1>TradeX Daily Sales Report</h1>
    <p><strong>Business date:</strong> {{ $reportDate }} ({{ $reportTimezone }})</p>

    <h2>Sales by Currency</h2>

    @forelse($report['summary']['currencies'] as $bucket)
        @php
            $gross = $money->present((int) $bucket['gross_minor'], $bucket['currency']);
            $fees = $money->present((int) $bucket['fees_minor'], $bucket['currency']);
            $adjustments = $money->present((int) $bucket['adjustments_minor'], $bucket['currency']);
            $knownNet = $money->present((int) $bucket['known_net_minor'], $bucket['currency']);
        @endphp

        <h3>{{ $bucket['currency'] }}</h3>
        <ul>
            <li>Payments: {{ number_format($bucket['payments_count']) }}</li>
            <li>Gross: {{ $gross['currency'] }} {{ $gross['amount'] }}</li>
            <li>Known processor fees: {{ $fees['currency'] }} {{ $fees['amount'] }}</li>
            <li>Payment adjustments/reversals: {{ $adjustments['currency'] }} {{ $adjustments['amount'] }}</li>
            <li>Known net after fees + adjustments: {{ $knownNet['currency'] }} {{ $knownNet['amount'] }}</li>
            <li>Payments with unknown processor fee: {{ number_format($bucket['unknown_fee_payments_count']) }}</li>
        </ul>
    @empty
        <p>No cleared sales or payment adjustments were recorded for this business date.</p>
    @endforelse

    @php
        $affiliate = $report['summary']['affiliate'];
        $commission = $money->present((int) $affiliate['net_commission_expense_cents'], 'USD');
        $gifts = $money->present((int) $affiliate['milestone_gifts_cents'], 'USD');
        $payouts = $money->present((int) $affiliate['cash_payouts_paid_cents'], 'USD');
        $reserved = $money->present((int) $report['active_payout_reserved_cents'], 'USD');
    @endphp

    <h2>Affiliate Finance — USD</h2>
    <ul>
        <li>Net commission accrued: USD {{ $commission['amount'] }}</li>
        <li>Milestone gifts accrued: USD {{ $gifts['amount'] }}</li>
        <li>Cash payouts settled today: USD {{ $payouts['amount'] }}</li>
        <li>Active payout balance reserved: USD {{ $reserved['amount'] }}</li>
    </ul>

    <h2>Operations</h2>
    <p>Pending manual payment reviews: {{ number_format($report['pending_manual_reviews']) }}</p>

    <p style="font-size:12px;color:#666;">
        TradeX does not combine USD, LKR and USDT into one profit figure without an approved FX conversion source.
        Paid affiliate payouts are cash settlement of previously accrued commission and are not deducted a second time from contribution.
    </p>
</body>
</html>
