@extends('admin.layouts.app')

@section('title', 'Screener Market Results | TradeX Administration')
@section('page-title', 'Screener Market Results')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/screener-monitor.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('admin/js/screener-crypto-live.js') }}" defer></script>
@endpush

@section('content')
<div class="screener-monitor-page screener-market-results-page">
    @include('admin.screener.partials.subnav')

    <div class="page-header screener-monitor-header">
        <div>
            <div class="page-eyebrow">MARKET DATA VIEW</div>
            <h1>Market <span>Results</span></h1>
            <p>Select a market and timeframe to view the latest screener output.</p>
        </div>
        @if(is_array($results))
            <div class="screener-monitor-actions">
                <span class="screener-overall-badge {{ data_get($results, 'cache.stale') ? 'status-degraded' : 'status-healthy' }}">
                    {{ data_get($results, 'cache.stale') ? 'STALE CACHE' : 'LIVE / FRESH' }}
                </span>
            </div>
        @endif
    </div>

    <section class="panel screener-market-controls">
        <div class="screener-market-buttons" aria-label="Select market">
            @foreach($markets as $marketKey => $marketConfig)
                <a
                    href="{{ route('admin.screener.markets', ['market' => $marketKey, 'timeframe' => $timeframe]) }}"
                    class="{{ $market === $marketKey ? 'active' : '' }} {{ ($marketConfig['enabled'] ?? false) ? '' : 'disabled' }}"
                    @if(!($marketConfig['enabled'] ?? false)) aria-disabled="true" @endif
                >
                    {{ $marketConfig['label'] ?? $marketKey }}
                    @if(!($marketConfig['enabled'] ?? false))
                        <small>Coming soon</small>
                    @endif
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.screener.markets') }}" class="screener-timeframe-form">
            <input type="hidden" name="market" value="{{ $market }}">
            @if($market === 'CRYPTO' && $search !== '')
                <input type="hidden" name="q" value="{{ $search }}">
            @endif
            <label for="screener-timeframe">Timeframe</label>
            <select id="screener-timeframe" name="timeframe" onchange="this.form.submit()">
                @foreach($timeframes as $timeframeKey => $timeframeConfig)
                    <option value="{{ $timeframeKey }}" @selected($timeframe === $timeframeKey)>
                        {{ $timeframeConfig['label'] ?? $timeframeKey }}
                    </option>
                @endforeach
            </select>
            <noscript><button type="submit" class="secondary-btn">View</button></noscript>
        </form>
    </section>

    @if($market === 'CRYPTO')
        <section class="panel screener-symbol-search-panel">
            <form method="GET" action="{{ route('admin.screener.markets') }}" class="screener-symbol-search-form" role="search">
                <input type="hidden" name="market" value="CRYPTO">
                <input type="hidden" name="timeframe" value="{{ $timeframe }}">
                <label for="screener-symbol-search">Search Binance symbols</label>
                <div>
                    <input
                        id="screener-symbol-search"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Search BTC, ETH, SOL…"
                        autocomplete="off"
                    >
                    <button type="submit" class="secondary-btn">Search</button>
                    @if($search !== '')
                        <a class="secondary-btn" href="{{ route('admin.screener.markets', ['market' => 'CRYPTO', 'timeframe' => $timeframe]) }}">Clear</a>
                    @endif
                </div>
            </form>
            @if($symbolPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <p>
                    {{ number_format($symbolPaginator->total()) }} Binance USDT Spot symbols
                    @if($search !== '') matching “{{ $search }}” @endif
                </p>
            @endif
        </section>
    @endif

    @if(is_array($error))
        <section class="panel screener-result-message status-degraded">
            <strong>{{ $error['code'] }}</strong>
            <p>{{ $error['message'] }}</p>
            <a class="secondary-btn" href="{{ route('admin.screener.markets', ['market' => $market, 'timeframe' => $timeframe]) }}">Try Again</a>
        </section>
    @elseif(is_array($results))
        <section class="panel screener-provider-panel">
            <div class="screener-panel-heading">
                <div>
                    <div class="panel-eyebrow">{{ $results['market'] }} · {{ $results['timeframe'] }}</div>
                    <h2>{{ $results['provider'] }} Screener Output</h2>
                </div>
                <span class="screener-generated-at">
                    @if($symbolPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        Showing {{ $symbolPaginator->firstItem() ?? 0 }}–{{ $symbolPaginator->lastItem() ?? 0 }} of {{ number_format($symbolPaginator->total()) }}
                    @else
                        {{ $results['count'] }} results
                    @endif
                    · {{ $results['generated_at_utc'] }}
                </span>
            </div>

            <div class="screener-table-scroll">
                <table
                    class="screener-provider-table screener-results-table"
                    @if($market === 'CRYPTO')
                        data-binance-live-table
                        data-analysis-url="{{ route('admin.screener.markets.analysis') }}"
                        data-analysis-timeframe="{{ $timeframe }}"
                    @endif
                >
                    <thead>
                        <tr>
                            <th>Symbol</th>
                            <th>Price</th>
                            <th>Change</th>
                            <th>Trend</th>
                            <th>RSI</th>
                            <th>EMA</th>
                            <th>ATR</th>
                            <th>Candle Time (UTC)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results['results'] as $row)
                            <tr @if($market === 'CRYPTO') data-binance-symbol="{{ $row['symbol'] }}" @endif>
                                <td>
                                    <div class="screener-symbol-cell">
                                        <span class="screener-symbol-logo" aria-hidden="true">
                                            {{ strtoupper(substr((string) ($row['base_currency'] ?? $row['symbol']), 0, 2)) }}
                                            @if(filled($row['logo_url'] ?? null))
                                                <img
                                                    src="{{ $row['logo_url'] }}"
                                                    alt=""
                                                    loading="lazy"
                                                    referrerpolicy="no-referrer"
                                                    onerror="this.hidden=true"
                                                >
                                            @endif
                                        </span>
                                        <span>
                                            @if($market === 'CRYPTO')
                                                <button
                                                    type="button"
                                                    class="screener-symbol-chart-link"
                                                    data-chart-symbol="{{ $row['symbol'] }}"
                                                    data-chart-name="{{ $row['name'] }}"
                                                    aria-label="Open {{ $row['symbol'] }} TradingView chart"
                                                >{{ $row['symbol'] }}</button>
                                            @else
                                                <strong>{{ $row['symbol'] }}</strong>
                                            @endif
                                            <small>{{ $row['name'] }}</small>
                                        </span>
                                    </div>
                                </td>
                                <td data-live-price>{{ $row['price'] ?? '—' }}</td>
                                <td data-live-change>{{ isset($row['change_percent']) ? number_format((float) $row['change_percent'], 2).'%' : '—' }}</td>
                                <td data-analysis-trend>
                                    @if(isset($row['trend']))
                                        <span class="screener-status trend-{{ strtolower($row['trend']) }}">{{ $row['trend'] }}</span>
                                    @else
                                        <span class="screener-lazy-value">Waiting…</span>
                                    @endif
                                </td>
                                <td data-analysis-rsi>{{ $row['rsi_14'] ?? '—' }}</td>
                                <td data-analysis-ema>{{ $row['ema_alignment'] ?? '—' }}</td>
                                <td data-analysis-atr>{{ isset($row['atr_percent']) ? number_format((float) $row['atr_percent'], 2).'%' : '—' }}</td>
                                <td data-analysis-time>{{ $row['candle_time_utc'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No screener results are available for this selection.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($symbolPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="screener-pagination">
                    @include('admin.components.pagination', ['paginator' => $symbolPaginator])
                </div>
            @endif
        </section>
    @endif

    @if($market === 'CRYPTO')
        <div class="screener-chart-modal" data-screener-chart-modal hidden>
            <div class="screener-chart-backdrop" data-chart-close></div>
            <section class="screener-chart-dialog" role="dialog" aria-modal="true" aria-labelledby="screener-chart-title">
                <header>
                    <div>
                        <div class="panel-eyebrow">BINANCE · TRADINGVIEW</div>
                        <h2 id="screener-chart-title" data-chart-title>Crypto Chart</h2>
                        <p>Clean candlestick chart · no default indicators</p>
                    </div>
                    <button type="button" class="screener-chart-close" data-chart-close aria-label="Close chart">×</button>
                </header>
                <div class="screener-tradingview-chart" data-tradingview-chart></div>
            </section>
        </div>
    @endif
</div>
@endsection
