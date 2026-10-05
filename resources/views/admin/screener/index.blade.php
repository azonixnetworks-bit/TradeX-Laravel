@extends('admin.layouts.app')

@section('title', 'Screener Monitor | TradeX Administration')
@section('page-title', 'Screener Monitor')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/screener-monitor.css').'?v='.filemtime(public_path('admin/css/screener-monitor.css')) }}">
@endpush

@section('content')
<div
    class="screener-monitor-page"
    id="screenerMonitorApp"
    data-health-endpoint="{{ route('admin.screener.health') }}"
>
    @include('admin.screener.partials.subnav')

    <div class="page-header screener-monitor-header">
        <div>
            <div class="page-eyebrow">MARKET DATA OPERATIONS</div>
            <h1>Screener <span>Monitor</span></h1>
            <p>
                Read-only health, cache and provider readiness for Forex, Crypto, Deriv and Stocks.
            </p>
        </div>

        <div class="screener-monitor-actions">
            <span
                class="screener-overall-badge status-{{ strtolower($snapshot['overall_status']) }}"
                id="screenerOverallStatus"
            >
                {{ $snapshot['overall_status'] }}
            </span>

            <a
                class="secondary-btn"
                href="{{ route('admin.screener.index') }}"
            >
                Refresh Status
            </a>
        </div>
    </div>

    <div class="screener-monitor-summary-grid">
        <section class="panel screener-summary-card">
            <span>Active Markets</span>
            <strong id="screenerActiveMarkets">{{ $snapshot['active_market_count'] }}</strong>
            <small>Provider-isolated markets</small>
        </section>

        <section class="panel screener-summary-card">
            <span>Providers</span>
            <strong id="screenerProviderCount">{{ $snapshot['provider_count'] }}</strong>
            <small>Unified health surface</small>
        </section>

        <section class="panel screener-summary-card">
            <span>Fresh Cache</span>
            <strong>{{ $snapshot['cache_policy']['fresh_seconds'] }}s</strong>
            <small>Normal dataset lifetime</small>
        </section>

        <section class="panel screener-summary-card">
            <span>Stale Fallback</span>
            <strong>{{ $snapshot['cache_policy']['stale_seconds'] }}s</strong>
            <small>Provider outage resilience window</small>
        </section>
    </div>

    <section class="panel screener-provider-panel">
        <div class="screener-panel-heading">
            <div>
                <div class="panel-eyebrow">PROVIDER HEALTH</div>
                <h2>Unified Market Data Status</h2>
            </div>
            <span class="screener-generated-at" id="screenerGeneratedAt">
                {{ $snapshot['generated_at_utc'] }}
            </span>
        </div>

        <div class="screener-table-scroll">
            <table class="screener-provider-table">
                <thead>
                    <tr>
                        <th>Market</th>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Configured</th>
                        <th>Last Success</th>
                        <th>Latency</th>
                        <th>Cache</th>
                        <th>Host</th>
                    </tr>
                </thead>
                <tbody id="screenerProviderRows">
                    @foreach($snapshot['providers'] as $provider)
                        <tr data-market="{{ $provider['market'] }}">
                            <td>
                                <strong>{{ $provider['market_label'] }}</strong>
                                <small>
                                    {{ $provider['market_status'] }} · {{ $provider['symbol_count'] }} symbols
                                </small>
                            </td>
                            <td>{{ $provider['provider'] }}</td>
                            <td>
                                <span class="screener-status status-{{ strtolower($provider['status']) }}">
                                    {{ $provider['status'] }}
                                </span>
                                @if($provider['last_error_code'])
                                    <small>{{ $provider['last_error_code'] }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $provider['configured'] ? 'YES' : 'NO' }}
                                @if(array_key_exists('external_display_approved', $provider))
                                    <small>
                                        Display rights: {{ $provider['external_display_approved'] ? 'APPROVED' : 'NOT APPROVED' }}
                                    </small>
                                @endif
                            </td>
                            <td>{{ $provider['last_success_at_utc'] ?? 'Never' }}</td>
                            <td>
                                {{ $provider['last_latency_ms'] !== null ? $provider['last_latency_ms'].' ms' : '—' }}
                            </td>
                            <td>
                                {{ $provider['cache']['fresh_datasets_cached'] }} fresh
                                <small>
                                    {{ $provider['cache']['fallback_datasets_cached'] }} fallback
                                </small>
                            </td>
                            <td>{{ $provider['base_host'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="screener-monitor-lower-grid">
        <section class="panel screener-policy-card">
            <div class="panel-eyebrow">CACHE RESILIENCE</div>
            <h2>Stale-data protection</h2>
            <p>
                Fresh datasets are preferred. If a provider temporarily fails, TradeX may serve the existing bounded fallback dataset and marks that API response as stale.
            </p>
            <dl>
                <div>
                    <dt>Fresh TTL</dt>
                    <dd>{{ $snapshot['cache_policy']['fresh_seconds'] }} sec</dd>
                </div>
                <div>
                    <dt>Fallback TTL</dt>
                    <dd>{{ $snapshot['cache_policy']['stale_seconds'] }} sec</dd>
                </div>
                <div>
                    <dt>Fallback enabled</dt>
                    <dd>{{ $snapshot['cache_policy']['stale_fallback_enabled'] ? 'YES' : 'NO' }}</dd>
                </div>
            </dl>
        </section>

        <section class="panel screener-policy-card">
            <div class="panel-eyebrow">SECURITY POSTURE</div>
            <h2>Secret-safe diagnostics</h2>
            <p>
                This screen reads local provider health and configuration state only. It does not perform live market-data requests and does not expose API keys, access tokens or credentials.
            </p>
            <dl>
                <div>
                    <dt>Phase</dt>
                    <dd>{{ $snapshot['phase'] }}</dd>
                </div>
                <div>
                    <dt>Network probe</dt>
                    <dd>NONE</dd>
                </div>
                <div>
                    <dt>Access</dt>
                    <dd>READ ONLY</dd>
                </div>
            </dl>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/screener-monitor.js') }}"></script>
@endpush
