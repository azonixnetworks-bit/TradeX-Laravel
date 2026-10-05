@extends('admin.layouts.app')

@section('title', $symbol.' Chart | TradeX Administration')
@section('page-title', $symbol.' Chart')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/screener-monitor.css').'?v='.filemtime(public_path('admin/css/screener-monitor.css')) }}">
@endpush

@section('content')
<div class="screener-monitor-page screener-chart-page">
    @include('admin.screener.partials.subnav')

    <section class="panel screener-chart-page-panel">
        <header class="screener-chart-page-header">
            <div>
                <div class="panel-eyebrow">BINANCE · TRADINGVIEW</div>
                <h1>{{ $symbol }} <span>· {{ $symbolConfig['name'] ?? 'Binance' }}</span></h1>
                <p>Clean candlestick chart · no default indicators</p>
            </div>
            <a class="secondary-btn" href="{{ route('admin.screener.markets', ['market' => 'CRYPTO', 'timeframe' => $timeframe]) }}">← Back to results</a>
        </header>

        <div class="screener-tradingview-chart screener-tradingview-chart-page">
            <div class="tradingview-widget-container">
                <div class="tradingview-widget-container__widget"></div>
                <script
                    type="text/javascript"
                    src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js"
                    async
                >
                    {
                        "autosize": true,
                        "symbol": "BINANCE:{{ $symbol }}",
                        "interval": "{{ $tradingViewInterval }}",
                        "timezone": "Etc/UTC",
                        "theme": "dark",
                        "style": "1",
                        "locale": "en",
                        "allow_symbol_change": false,
                        "hide_side_toolbar": true,
                        "hide_top_toolbar": false,
                        "hide_legend": false,
                        "hide_volume": true,
                        "studies": [],
                        "withdateranges": true,
                        "save_image": false,
                        "calendar": false,
                        "support_host": "https://www.tradingview.com"
                    }
                </script>
            </div>
        </div>
    </section>
</div>
@endsection
