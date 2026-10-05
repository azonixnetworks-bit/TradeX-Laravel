@extends('admin.layouts.app')

@section('title', 'Screener Market Results | TradeX Administration')
@section('page-title', 'Screener Market Results')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/screener-monitor.css') }}">
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
                    {{ $results['count'] }} results · {{ $results['generated_at_utc'] }}
                </span>
            </div>

            <div class="screener-table-scroll">
                <table class="screener-provider-table screener-results-table">
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
                            <tr>
                                <td><strong>{{ $row['symbol'] }}</strong><small>{{ $row['name'] }}</small></td>
                                <td>{{ $row['price'] ?? '—' }}</td>
                                <td>{{ isset($row['change_percent']) ? number_format((float) $row['change_percent'], 2).'%' : '—' }}</td>
                                <td><span class="screener-status trend-{{ strtolower($row['trend'] ?? 'sideways') }}">{{ $row['trend'] ?? '—' }}</span></td>
                                <td>{{ $row['rsi_14'] ?? '—' }}</td>
                                <td>{{ $row['ema_alignment'] ?? '—' }}</td>
                                <td>{{ isset($row['atr_percent']) ? number_format((float) $row['atr_percent'], 2).'%' : '—' }}</td>
                                <td>{{ $row['candle_time_utc'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No screener results are available for this selection.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
