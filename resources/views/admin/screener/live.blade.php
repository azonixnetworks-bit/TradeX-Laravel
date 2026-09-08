@extends('admin.layouts.app')

@section('title', 'Live Screener | TradeX Administration')
@section('page-title', 'Live Screener')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/screener-live.css') }}">
@endpush

@section('content')
@php
    $liveScreenerConfig = [
        'markets' => $markets,
        'timeframes' => $timeframes,
        'stock_regions' => $stockRegions,
        'health' => $health,
    ];
@endphp

<div
    class="live-screener-page"
    id="liveScreenerApp"
    data-endpoint="{{ route('admin.screener.live_data') }}"
    data-monitor-url="{{ route('admin.screener.index') }}"
>
    <script type="application/json" id="liveScreenerConfig">@json($liveScreenerConfig)</script>

    <div class="page-header live-screener-header">
        <div>
            <div class="page-eyebrow">LIVE MARKET ANALYSIS</div>
            <h1>Live <span>Screener</span></h1>
            <p>
                Forex, Crypto, Deriv and approved Stocks data through the frozen TradeX screener engine.
            </p>
        </div>

        <div class="live-screener-header-actions">
            <span class="live-screener-pill" id="liveScreenerConnectionBadge">READY</span>
            <button type="button" class="secondary-btn" id="liveScreenerRefreshButton">
                Refresh Data
            </button>
            <a class="secondary-btn" href="{{ route('admin.screener.index') }}">
                Provider Monitor
            </a>
        </div>
    </div>

    <section class="panel live-screener-control-panel" aria-label="Screener filters">
        <div class="live-screener-market-tabs" id="liveScreenerMarketTabs" role="tablist" aria-label="Markets"></div>

        <div class="live-screener-filter-grid">
            <div class="live-screener-filter-block">
                <span class="live-screener-filter-label">Timeframe</span>
                <div class="live-screener-timeframes" id="liveScreenerTimeframes"></div>
            </div>

            <label class="live-screener-field live-screener-stock-only" id="liveScreenerRegionField" hidden>
                <span>Stock Region</span>
                <select id="liveScreenerRegion"></select>
            </label>

            <label class="live-screener-field live-screener-stock-only" id="liveScreenerExchangeField" hidden>
                <span>Exchange</span>
                <select id="liveScreenerExchange">
                    <option value="">All Exchanges</option>
                </select>
            </label>

            <label class="live-screener-field">
                <span>Trend</span>
                <select id="liveScreenerTrend">
                    <option value="">All Trends</option>
                    <option value="BULLISH">Bullish</option>
                    <option value="BEARISH">Bearish</option>
                    <option value="SIDEWAYS">Sideways</option>
                </select>
            </label>

            <label class="live-screener-field">
                <span>EMA Alignment</span>
                <select id="liveScreenerAlignment">
                    <option value="">All Alignments</option>
                    <option value="BULLISH">Bullish</option>
                    <option value="BEARISH">Bearish</option>
                    <option value="MIXED">Mixed</option>
                </select>
            </label>

            <label class="live-screener-field">
                <span>Sort</span>
                <select id="liveScreenerSort">
                    <option value="symbol">Symbol</option>
                    <option value="change_percent">Change %</option>
                    <option value="rsi">RSI</option>
                    <option value="atr_percent">ATR %</option>
                    <option value="trend">Trend</option>
                </select>
            </label>

            <label class="live-screener-field">
                <span>Order</span>
                <select id="liveScreenerOrder">
                    <option value="asc">Ascending</option>
                    <option value="desc">Descending</option>
                </select>
            </label>

            <label class="live-screener-field live-screener-search-field">
                <span>Search Symbol</span>
                <input
                    id="liveScreenerSearch"
                    type="search"
                    maxlength="32"
                    autocomplete="off"
                    placeholder="EURUSD, BTCUSDT, AAPL..."
                >
            </label>
        </div>

        <div class="live-screener-rsi-row">
            <label class="live-screener-field live-screener-number-field">
                <span>RSI Min</span>
                <input id="liveScreenerRsiMin" type="number" min="0" max="100" step="1" placeholder="0">
            </label>
            <label class="live-screener-field live-screener-number-field">
                <span>RSI Max</span>
                <input id="liveScreenerRsiMax" type="number" min="0" max="100" step="1" placeholder="100">
            </label>
            <label class="live-screener-auto-refresh">
                <input type="checkbox" id="liveScreenerAutoRefresh" checked>
                <span>Auto refresh every 30 sec</span>
            </label>
        </div>
    </section>

    <div class="live-screener-status-grid">
        <section class="panel live-screener-status-card">
            <span>Provider</span>
            <strong id="liveScreenerProvider">—</strong>
            <small id="liveScreenerProviderStatus">Waiting for data</small>
        </section>
        <section class="panel live-screener-status-card">
            <span>Rows</span>
            <strong id="liveScreenerCount">0</strong>
            <small id="liveScreenerMarketLabel">FOREX · H1</small>
        </section>
        <section class="panel live-screener-status-card">
            <span>Dataset</span>
            <strong id="liveScreenerCacheState">—</strong>
            <small id="liveScreenerGeneratedAt">Not loaded</small>
        </section>
        <section class="panel live-screener-status-card">
            <span>Refresh</span>
            <strong id="liveScreenerRefreshState">30s</strong>
            <small>Internal cache remains authoritative</small>
        </section>
    </div>

    <div class="live-screener-alert" id="liveScreenerAlert" hidden role="alert"></div>

    <section class="panel live-screener-table-panel">
        <div class="live-screener-table-heading">
            <div>
                <div class="panel-eyebrow">TECHNICAL SNAPSHOT</div>
                <h2 id="liveScreenerTableTitle">Forex · H1</h2>
            </div>
            <div class="live-screener-legend">
                <span><i class="legend-dot bullish"></i> Bullish</span>
                <span><i class="legend-dot bearish"></i> Bearish</span>
                <span><i class="legend-dot sideways"></i> Sideways</span>
            </div>
        </div>

        <div class="live-screener-loading" id="liveScreenerLoading" hidden>
            Loading screener data…
        </div>

        <div class="live-screener-table-scroll">
            <table class="live-screener-table">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Price</th>
                        <th>Change %</th>
                        <th>Trend</th>
                        <th>EMA Align</th>
                        <th>EMA 20</th>
                        <th>EMA 50</th>
                        <th>EMA 200</th>
                        <th>RSI 14</th>
                        <th>MACD Hist.</th>
                        <th>ATR %</th>
                        <th>Volatility</th>
                        <th>Candle UTC</th>
                    </tr>
                </thead>
                <tbody id="liveScreenerRows">
                    <tr>
                        <td colspan="13" class="live-screener-empty">Select a market to load data.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <p class="live-screener-footnote">
        Change % is calculated against the previous candle for the selected timeframe. Cached or stale fallback data is always labelled in the status cards.
    </p>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/screener-live.js') }}"></script>
@endpush
