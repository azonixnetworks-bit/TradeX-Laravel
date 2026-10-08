@extends('admin.layouts.app')

@section('title', 'Market Bubbles | TradeX Administration')
@section('page-title', 'Market Bubbles')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('admin/css/screener-monitor.css').'?v='.filemtime(public_path('admin/css/screener-monitor.css')) }}"
>
@endpush

@push('scripts')
<script
    src="{{ asset('admin/js/market-bubbles.js').'?v='.filemtime(public_path('admin/js/market-bubbles.js')) }}"
    defer
></script>
@endpush

@section('content')
<div class="screener-monitor-page market-bubbles-page">
    @include('admin.screener.partials.subnav')

    <div class="page-header screener-monitor-header">
        <div>
            <div class="page-eyebrow">LIVE MARKET VISUALISATION</div>
            <h1>Market <span>Bubbles</span></h1>
            <p>
                Crypto strength, weakness and trading activity in one live visual map.
            </p>
        </div>

        @if(is_array($dataset))
            <div class="screener-monitor-actions">
                <span
                    class="screener-overall-badge {{ ($dataset['stale'] ?? false) ? 'status-degraded' : 'status-healthy' }}"
                    data-bubble-connection-badge
                >
                    {{ ($dataset['stale'] ?? false) ? 'DELAYED DATA' : 'LIVE / FRESH' }}
                </span>
                <span class="screener-generated-at" data-bubble-updated-at>
                    {{ $dataset['generated_at_utc'] ?? '' }}
                </span>
            </div>
        @endif
    </div>

    @if(is_array($error))
        <section class="panel screener-result-message status-degraded">
            <strong>{{ $error['code'] }}</strong>
            <p>{{ $error['message'] }}</p>
            <a class="secondary-btn" href="{{ route('admin.screener.bubbles') }}">
                Try Again
            </a>
        </section>
    @elseif(is_array($dataset))
        <section class="market-bubble-market-tabs" aria-label="Bubble market">
            <button type="button" class="active">Crypto</button>
            <button type="button" disabled>Forex <small>Planned</small></button>
            <button type="button" disabled>Deriv <small>Planned</small></button>
            <button type="button" disabled>Stocks <small>Planned</small></button>
        </section>

        <div class="market-bubble-summary-grid">
            <section class="panel market-bubble-summary-card">
                <span>Total Assets</span>
                <strong data-summary-total>{{ $dataset['summary']['total_assets'] ?? 0 }}</strong>
                <small>Top USDT spot markets by 24H quote volume</small>
            </section>
            <section class="panel market-bubble-summary-card">
                <span>Gainers</span>
                <strong data-summary-gainers>{{ $dataset['summary']['gainers'] ?? 0 }}</strong>
                <small>Positive 24H change</small>
            </section>
            <section class="panel market-bubble-summary-card">
                <span>Losers</span>
                <strong data-summary-losers>{{ $dataset['summary']['losers'] ?? 0 }}</strong>
                <small>Negative 24H change</small>
            </section>
            <section class="panel market-bubble-summary-card">
                <span>Average Change</span>
                <strong data-summary-average>
                    {{ number_format((float) ($dataset['summary']['average_change'] ?? 0), 2) }}%
                </strong>
                <small>Across currently loaded assets</small>
            </section>
        </div>

        <section class="panel market-bubble-controls">
            <div class="market-bubble-control">
                <label for="bubbleSearch">Search</label>
                <input
                    id="bubbleSearch"
                    type="search"
                    placeholder="BTC, ETH, SOL..."
                    autocomplete="off"
                    data-bubble-search
                >
            </div>

            <div class="market-bubble-control">
                <label for="bubbleSort">Sort</label>
                <select id="bubbleSort" data-bubble-sort>
                    <option value="volume">Highest Volume</option>
                    <option value="gainers">Biggest Gainers</option>
                    <option value="losers">Biggest Losers</option>
                    <option value="symbol">Alphabetical</option>
                </select>
            </div>

            <div class="market-bubble-control">
                <label for="bubbleSize">Bubble Size By</label>
                <select id="bubbleSize" data-bubble-size>
                    <option value="volume">Trading Volume</option>
                    <option value="change">Price Change</option>
                    <option value="equal">Equal Size</option>
                </select>
            </div>

            <div class="market-bubble-control">
                <label for="bubbleCount">Assets</label>
                <select id="bubbleCount" data-bubble-count>
                    <option value="20">Top 20</option>
                    <option value="50">Top 50</option>
                    <option value="100" selected>Top 100</option>
                </select>
            </div>

            <div class="market-bubble-timeframe" aria-label="Timeframe">
                <span>Timeframe</span>
                <button type="button" class="active">24H</button>
                <button type="button" disabled>1H</button>
                <button type="button" disabled>4H</button>
                <button type="button" disabled>7D</button>
                <button type="button" disabled>30D</button>
            </div>
        </section>

        <section
            class="panel market-bubble-stage"
            data-market-bubbles
            data-chart-url="{{ route('admin.screener.markets.chart') }}"
        >
            <script type="application/json" data-bubble-assets>@json($dataset["assets"] ?? [])</script>
            <div class="market-bubble-stage-head">
                <div>
                    <div class="panel-eyebrow">CRYPTO · BINANCE · 24H</div>
                    <h2>Live Market Map</h2>
                </div>
                <div class="market-bubble-legend" aria-label="Performance legend">
                    <span><i class="positive"></i> Gain</span>
                    <span><i class="neutral"></i> Flat</span>
                    <span><i class="negative"></i> Loss</span>
                </div>
            </div>

            <div class="market-bubble-canvas-wrap">
                <canvas
                    data-bubble-canvas
                    aria-label="Interactive crypto market bubble map"
                ></canvas>
                <div class="market-bubble-loading" data-bubble-loading>
                    <span class="market-bubble-loading-spinner" aria-hidden="true"></span>
                    <span data-bubble-loading-text>Loading market map…</span>
                </div>
                <div class="market-bubble-empty" data-bubble-empty hidden>
                    No assets match this filter.
                </div>
            </div>

            <p class="market-bubble-note">
                Bubble size uses Binance quote-volume by default. Market-cap sizing is intentionally not shown because the current Binance spot feed does not provide circulating market capitalisation.
            </p>
        </section>

        <aside class="market-bubble-detail" data-bubble-detail hidden>
            <button
                type="button"
                class="market-bubble-detail-backdrop"
                data-bubble-close
                aria-label="Close asset details"
            ></button>

            <section class="market-bubble-detail-card" role="dialog" aria-modal="true" aria-labelledby="bubbleDetailTitle">
                <header>
                    <div class="market-bubble-detail-symbol">
                        <span class="screener-symbol-logo" data-detail-logo-wrap>
                            <span data-detail-initials>--</span>
                            <img data-detail-logo alt="" hidden>
                        </span>
                        <div>
                            <div class="panel-eyebrow">ASSET DETAIL</div>
                            <h2 id="bubbleDetailTitle" data-detail-symbol>—</h2>
                            <p data-detail-name>—</p>
                        </div>
                    </div>
                    <button type="button" class="market-bubble-detail-close" data-bubble-close>×</button>
                </header>

                <div class="market-bubble-detail-price">
                    <strong data-detail-price>—</strong>
                    <span data-detail-change>—</span>
                </div>

                <dl class="market-bubble-detail-grid">
                    <div><dt>24H Open</dt><dd data-detail-open>—</dd></div>
                    <div><dt>24H High</dt><dd data-detail-high>—</dd></div>
                    <div><dt>24H Low</dt><dd data-detail-low>—</dd></div>
                    <div><dt>Quote Volume</dt><dd data-detail-volume>—</dd></div>
                    <div><dt>Base Volume</dt><dd data-detail-base-volume>—</dd></div>
                    <div><dt>Trades</dt><dd data-detail-trades>—</dd></div>
                </dl>

                <a class="primary-btn" data-detail-chart href="#">
                    Open H1 Chart
                </a>
            </section>
        </aside>
    @endif
</div>
@endsection
