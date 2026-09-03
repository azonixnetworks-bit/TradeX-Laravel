<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Investment Agreement {{ $investment->investment_code }} v{{ $agreementVersion }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color:#111; line-height:1.55; max-width:900px; margin:40px auto; padding:0 24px; }
        h1,h2 { margin-bottom:8px; }
        h2 { margin-top:28px; border-bottom:1px solid #bbb; padding-bottom:6px; }
        table { width:100%; border-collapse:collapse; margin:16px 0; }
        td,th { border:1px solid #bbb; padding:9px; text-align:left; vertical-align:top; }
        .notice { padding:14px; border:2px solid #222; margin:18px 0; font-weight:700; }
        .small { font-size:12px; color:#444; }
        .signature { margin-top:60px; display:grid; grid-template-columns:1fr 1fr; gap:50px; }
        .line { border-top:1px solid #111; padding-top:8px; margin-top:50px; }
    </style>
</head>
<body>
    <h1>Performance-Based Crypto Investment Agreement</h1>
    <p><strong>Agreement version:</strong> {{ $agreementVersion }}</p>
    <p><strong>Template version:</strong> {{ $templateVersion }}</p>
    <p><strong>Template status:</strong> {{ $templateStatus }}</p>
    <p><strong>Generated:</strong> {{ $generatedAt->toIso8601String() }}</p>

    @if($templateStatus !== 'APPROVED')
        <div class="notice">
            This template is marked {{ $templateStatus }}. It must receive qualified legal review before production activation where the TradeX production legal gate is enabled.
        </div>
    @endif

    <h2>1. Parties and Investment</h2>
    <p>
        This agreement records the investment terms between
        <strong>{{ $companyLegalName }}</strong> and
        <strong>{{ $investment->user?->name }}</strong>
        for TradeX investment reference
        <strong>{{ $investment->investment_code }}</strong>.
    </p>

    <table>
        <tr><th>Product</th><td>{{ $investment->product?->name }}</td></tr>
        <tr><th>Investment Amount</th><td>{{ $investment->deposit_currency }} {{ $investment->deposit_amount }}</td></tr>
        <tr><th>Deposit Network</th><td>{{ $investment->deposit_network }}</td></tr>
        <tr><th>Investment Period</th><td>{{ $investment->investment_period_years }} year(s)</td></tr>
        <tr><th>Profit Review / Distribution</th><td>Every {{ $investment->profit_review_interval_months }} months</td></tr>
        <tr><th>Profit Model</th><td>Performance-based net realized profit sharing</td></tr>
        <tr><th>Customer Share</th><td>{{ $investment->customer_profit_share_bps / 100 }}% of eligible net realized profit</td></tr>
        <tr><th>Company Share</th><td>{{ $investment->company_profit_share_bps / 100 }}% of eligible net realized profit</td></tr>
        <tr><th>Fixed / Guaranteed Return</th><td>NO</td></tr>
        <tr><th>Principal Settlement Mode</th><td>{{ $investment->settlement_mode }}</td></tr>
        @if($investment->custom_settlement_date)
            <tr><th>Custom Principal Settlement Date</th><td>{{ $investment->custom_settlement_date->toDateString() }}</td></tr>
        @endif
    </table>

    <h2>2. Performance-Based Profit Sharing</h2>
    <p>
        No fixed annual return is promised. Profit sharing applies only to eligible
        net realized profit determined under the TradeX investment ledger and the
        executed agreement. The customer share is
        <strong>{{ $investment->customer_profit_share_bps / 100 }}%</strong>
        and the company performance share is
        <strong>{{ $investment->company_profit_share_bps / 100 }}%</strong>.
        If there is no eligible positive net realized profit for a review period,
        no performance-profit distribution is due for that period.
    </p>

    <h2>3. Loss Carry-Forward and High-Water Mark</h2>
    <p>
        Prior investment losses must be recovered before new eligible performance
        profit is treated as distributable. The investment ledger uses loss
        carry-forward and high-water-mark controls so that the company does not
        receive a performance share merely because a later period partially
        recovers an earlier loss.
    </p>

    <h2>4. {{ $riskTitle }}</h2>
    <p><strong>Disclosure version:</strong> {{ $riskVersion }}</p>
    <ol>
        @foreach($riskStatements as $statement)
            <li>{{ $statement }}</li>
        @endforeach
    </ol>

    <h2>5. Early Termination / Agreement Break</h2>
    <p>
        If the customer terminates before the agreed maturity date, the original
        USDT deposit amount is not guaranteed to be returned. Subject to applicable
        law and the executed agreement, the agreed early-exit rule is an in-kind
        return of <strong>{{ $investment->early_exit_return_bps / 100 }}%</strong>
        of the customer's attributable underlying spot-crypto asset units.
        The market value of returned assets may be materially higher or lower than
        the original USDT investment amount. Blockchain/network transfer fees may
        apply.
    </p>
    <p>
        The detailed treatment of any accrued-but-unpaid profit at early
        termination is not implemented by ADMIN-24 Phase 2 and must be locked in
        the legally reviewed early-termination policy before that workflow is
        enabled.
    </p>

    <h2>6. Customer Risk-Acceptance Evidence</h2>
    <table>
        <tr><th>Acceptance Reference</th><td>{{ $riskAcceptance->acceptance_reference }}</td></tr>
        <tr><th>Accepted At</th><td>{{ $riskAcceptance->accepted_at?->toIso8601String() }}</td></tr>
        <tr><th>Evidence Source</th><td>{{ $riskAcceptance->evidence_source }}</td></tr>
        <tr><th>Risk Version</th><td>{{ $riskAcceptance->disclosure_version }}</td></tr>
    </table>

    <h2>7. Regulatory and Legal Status</h2>
    <p>
        Nothing in this agreement should be represented as a government-approved
        profit rate or a guaranteed investment return. Crypto-related legal and
        regulatory requirements can change. The final production contract must be
        reviewed for applicable Sri Lankan law and any other law governing the
        parties.
    </p>

    <h2>8. Signatures</h2>
    <div class="signature">
        <div><div class="line">Customer Signature / Date</div></div>
        <div><div class="line">{{ $companyLegalName }} Authorized Signatory / Date</div></div>
    </div>

    <p class="small">
        Immutable generated document reference:
        {{ $investment->investment_code }} / agreement v{{ $agreementVersion }} /
        {{ $templateVersion }} / {{ $riskVersion }}.
    </p>
</body>
</html>
