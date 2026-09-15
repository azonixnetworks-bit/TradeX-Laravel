@extends('admin.layouts.app')

@section('title', 'Risk Reward Calculator | TradeX Administration')
@section('page-title', 'Risk Reward Calculator')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/risk-reward-calculator.css') }}">
@endpush

@section('content')
@php
    $defaultMarketKey = array_key_first($markets) ?: 'forex';
@endphp

<div
    class="risk-reward-page"
    id="riskRewardCalculatorApp"
    data-calculate-endpoint="{{ route('admin.risk_reward.calculate') }}"
    data-default-market="{{ $defaultMarketKey }}"
>
    <div class="page-header risk-reward-header">
        <div>
            <div class="page-eyebrow">TRADING TOOLS</div>
            <h1>TradeX <span>Risk Reward Calculator</span></h1>
            <p>
                Calculate position size, estimated risk, potential profit and risk-to-reward before placing a trade.
            </p>
        </div>

        <div class="risk-reward-header-badge">
            <span class="risk-reward-header-dot"></span>
            POSITION SIZING
        </div>
    </div>

    <section class="panel risk-reward-market-panel" aria-label="Market selection">
        <div class="risk-reward-market-tabs" role="tablist" aria-label="Calculator market">
            @foreach($markets as $marketKey => $market)
                <button
                    type="button"
                    class="risk-reward-market-tab {{ $marketKey === $defaultMarketKey ? 'active' : '' }}"
                    data-market-key="{{ $marketKey }}"
                    role="tab"
                    aria-selected="{{ $marketKey === $defaultMarketKey ? 'true' : 'false' }}"
                >
                    {{ $market['label'] ?? ucfirst($marketKey) }}
                </button>
            @endforeach
        </div>

        <p class="risk-reward-market-description" id="riskRewardMarketDescription"></p>
    </section>

    <div class="risk-reward-layout">
        <section class="panel risk-reward-form-panel">
            <div class="risk-reward-panel-heading">
                <div>
                    <div class="panel-eyebrow">TRADE INPUT</div>
                    <h2>Position Setup</h2>
                </div>
                <span class="risk-reward-step">01</span>
            </div>

            <form id="riskRewardForm" novalidate>
                <input
                    type="hidden"
                    name="market"
                    id="riskRewardMarket"
                    value="{{ $defaultMarketKey }}"
                >

                <div class="risk-reward-field">
                    <label for="riskRewardInstrument">Instrument</label>
                    <div class="risk-reward-search-control">
                        <span aria-hidden="true">⌕</span>
                        <input
                            id="riskRewardInstrument"
                            name="symbol"
                            type="text"
                            list="riskRewardInstrumentList"
                            autocomplete="off"
                            placeholder="Type or select a symbol"
                            required
                        >
                    </div>
                    <datalist id="riskRewardInstrumentList"></datalist>
                    <small class="risk-reward-field-help" id="riskRewardInstrumentHelp">
                        Start typing a symbol, for example EURUSD.
                    </small>
                    <div class="risk-reward-field-error" data-error-for="symbol"></div>
                </div>

                <fieldset class="risk-reward-field risk-reward-side-field">
                    <legend>Trade Type</legend>
                    <div class="risk-reward-side-toggle">
                        <button
                            type="button"
                            class="risk-reward-side-button buy active"
                            data-side="BUY"
                            aria-pressed="true"
                        >
                            BUY
                        </button>
                        <button
                            type="button"
                            class="risk-reward-side-button sell"
                            data-side="SELL"
                            aria-pressed="false"
                        >
                            SELL
                        </button>
                    </div>
                    <input type="hidden" name="side" id="riskRewardSide" value="BUY">
                    <div class="risk-reward-field-error" data-error-for="side"></div>
                </fieldset>

                <div class="risk-reward-price-grid">
                    <div class="risk-reward-field risk-reward-field-full">
                        <label for="riskRewardEntry">Entry Price</label>
                        <input
                            id="riskRewardEntry"
                            name="entry"
                            type="number"
                            min="0"
                            step="any"
                            inputmode="decimal"
                            placeholder="0.00000"
                            required
                        >
                        <div class="risk-reward-field-error" data-error-for="entry"></div>
                    </div>

                    <div class="risk-reward-field">
                        <label for="riskRewardStopLoss">Stop Loss</label>
                        <input
                            id="riskRewardStopLoss"
                            name="stop_loss"
                            type="number"
                            min="0"
                            step="any"
                            inputmode="decimal"
                            placeholder="0.00000"
                            required
                        >
                        <div class="risk-reward-field-error" data-error-for="stop_loss"></div>
                    </div>

                    <div class="risk-reward-field">
                        <label for="riskRewardTakeProfit">Take Profit</label>
                        <input
                            id="riskRewardTakeProfit"
                            name="take_profit"
                            type="number"
                            min="0"
                            step="any"
                            inputmode="decimal"
                            placeholder="0.00000"
                            required
                        >
                        <div class="risk-reward-field-error" data-error-for="take_profit"></div>
                    </div>
                </div>

                <div class="risk-reward-field">
                    <label for="riskRewardAmount">Risk Amount</label>
                    <div class="risk-reward-money-control">
                        <span>$</span>
                        <input
                            id="riskRewardAmount"
                            name="risk_amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="100.00"
                            required
                        >
                        <strong>USD</strong>
                    </div>
                    <small class="risk-reward-field-help">
                        Maximum amount you are prepared to lose if Stop Loss is reached.
                    </small>
                    <div class="risk-reward-field-error" data-error-for="risk_amount"></div>
                </div>

                <div
                    class="risk-reward-form-message"
                    id="riskRewardFormMessage"
                    role="alert"
                    aria-live="polite"
                    hidden
                ></div>

                <div class="risk-reward-form-actions">
                    <button type="submit" class="primary-btn" id="riskRewardCalculateButton">
                        Calculate Position
                    </button>
                    <button type="button" class="secondary-btn" id="riskRewardResetButton">
                        Reset
                    </button>
                </div>
            </form>
        </section>

        <section class="panel risk-reward-result-panel" aria-live="polite">
            <div class="risk-reward-panel-heading">
                <div>
                    <div class="panel-eyebrow">CALCULATION RESULT</div>
                    <h2>Risk Summary</h2>
                </div>
                <span class="risk-reward-step">02</span>
            </div>

            <div class="risk-reward-primary-result">
                <span>Recommended Lot Size</span>
                <strong id="riskRewardLotSize">—</strong>
                <small id="riskRewardLotStatus">Complete the trade setup to calculate.</small>
            </div>

            <div class="risk-reward-result-grid">
                <div class="risk-reward-result-card">
                    <span>Actual Risk</span>
                    <strong id="riskRewardActualRisk">—</strong>
                </div>
                <div class="risk-reward-result-card">
                    <span>Potential Profit</span>
                    <strong id="riskRewardPotentialProfit">—</strong>
                </div>
                <div class="risk-reward-result-card">
                    <span>Risk : Reward</span>
                    <strong id="riskRewardRatio">—</strong>
                </div>
                <div class="risk-reward-result-card">
                    <span>Risk Per 1 Lot</span>
                    <strong id="riskRewardPerLot">—</strong>
                </div>
                <div class="risk-reward-result-card">
                    <span>SL Distance</span>
                    <strong id="riskRewardSlDistance">—</strong>
                </div>
                <div class="risk-reward-result-card">
                    <span>TP Distance</span>
                    <strong id="riskRewardTpDistance">—</strong>
                </div>
            </div>

            <div class="risk-reward-warning" id="riskRewardWarning" hidden></div>

            <div class="risk-reward-spec-card">
                <div class="risk-reward-spec-heading">
                    <span>Instrument Specification</span>
                    <strong id="riskRewardSpecSymbol">—</strong>
                </div>
                <dl>
                    <div>
                        <dt>Contract Size</dt>
                        <dd id="riskRewardContractSize">—</dd>
                    </div>
                    <div>
                        <dt>Minimum Lot</dt>
                        <dd id="riskRewardMinLot">—</dd>
                    </div>
                    <div>
                        <dt>Maximum Lot</dt>
                        <dd id="riskRewardMaxLot">—</dd>
                    </div>
                    <div>
                        <dt>Lot Step</dt>
                        <dd id="riskRewardLotStep">—</dd>
                    </div>
                    <div>
                        <dt>Profile</dt>
                        <dd id="riskRewardSpecProfile">—</dd>
                    </div>
                    <div>
                        <dt>Spec Status</dt>
                        <dd id="riskRewardSpecStatus">—</dd>
                    </div>
                    <div class="risk-reward-spec-wide">
                        <dt>Source</dt>
                        <dd id="riskRewardSpecSource">—</dd>
                    </div>
                    <div>
                        <dt>Reviewed</dt>
                        <dd id="riskRewardSpecReviewed">—</dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    <section class="panel risk-reward-notice">
        <div class="risk-reward-notice-icon" aria-hidden="true">!</div>
        <div>
            <strong>Risk Management Notice</strong>
            <p>{{ $disclaimer }}</p>
        </div>
    </section>

    <script type="application/json" id="riskRewardMarketData">@json($markets)</script>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/risk-reward-calculator.js') }}"></script>
@endpush