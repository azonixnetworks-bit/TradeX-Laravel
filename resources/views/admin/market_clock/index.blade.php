@extends('admin.layouts.app')

@section('title', 'Market Clock | TradeX Administration')
@section('page-title', 'Market Clock')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/market-clock.css') }}">
@endpush

@section('content')
<div
    class="market-clock-page"
    id="marketClockApp"
    data-endpoint="{{ route('admin.market_clock.data') }}"
>
    <div class="page-header market-clock-header">
        <div>
            <div class="page-eyebrow">GLOBAL TRADING SESSIONS</div>
            <h1>Market <span>Clock</span></h1>
            <p>
                One 24-hour global view, calculated from UTC with DST-aware IANA timezones.
            </p>
        </div>

        <div class="market-clock-header-actions">
            <div class="market-live-pill" id="marketLivePill">
                <span class="market-live-dot" aria-hidden="true"></span>
                <span id="marketLiveLabel">LIVE</span>
            </div>

            <label class="market-timezone-field" for="marketClockTimezone">
                <span>Timezone view</span>
                <select id="marketClockTimezone" aria-label="Market clock timezone">
                    @foreach($timezones as $ianaTimezone)
                        <option
                            value="{{ $ianaTimezone }}"
                            @selected($ianaTimezone === $timezone)
                        >
                            {{ $ianaTimezone }}
                        </option>
                    @endforeach
                </select>
            </label>

            <button
                type="button"
                class="secondary-btn market-auto-timezone"
                id="marketClockUseDeviceTimezone"
            >
                Use Device Timezone
            </button>
        </div>
    </div>

    <div class="market-clock-hero-grid">
        <section class="panel market-dial-panel" aria-labelledby="marketDialHeading">
            <div class="market-panel-heading">
                <div>
                    <div class="panel-eyebrow">24 HOUR VISUAL CLOCK</div>
                    <h2 id="marketDialHeading">Forex Trading Centres</h2>
                </div>
                <span class="market-timezone-chip" id="marketTimezoneChip">{{ $timezone }}</span>
            </div>

            <div class="market-dial-wrap">
                <svg
                    id="marketClockDial"
                    class="market-clock-dial"
                    viewBox="0 0 700 700"
                    role="img"
                    aria-label="24 hour global forex market session clock"
                ></svg>
            </div>

            <div class="market-dial-legend" aria-label="Market clock legend">
                <span><i class="legend-dot legend-session"></i> Session window</span>
                <span><i class="legend-dot legend-active"></i> Active now</span>
                <span><i class="legend-line"></i> Current time</span>
            </div>
        </section>

        <div class="market-clock-side-grid">
            <section class="panel market-info-card">
                <div class="market-info-icon">◷</div>
                <div class="market-info-copy">
                    <span>Current Time</span>
                    <strong id="marketCurrentTime">{{ $snapshot['display_time'] }}</strong>
                    <small id="marketCurrentDate">
                        {{ $snapshot['display_date'] }} · UTC{{ $snapshot['display_offset'] }}
                    </small>
                </div>
            </section>

            <section class="panel market-info-card">
                <div class="market-info-icon active">◉</div>
                <div class="market-info-copy">
                    <span>Active Sessions</span>
                    <strong id="marketActiveCount">{{ $snapshot['summary']['open_now'] }}</strong>
                    <small id="marketActiveNames">
                        {{ collect($snapshot['active_sessions'])->pluck('name')->join(', ') ?: 'Between sessions' }}
                    </small>
                </div>
            </section>

            <section class="panel market-info-card">
                <div class="market-info-icon next">»</div>
                <div class="market-info-copy">
                    <span>Next Open</span>
                    <strong id="marketNextName">{{ $snapshot['next_session']['name'] ?? '—' }}</strong>
                    <small id="marketNextCountdown">Calculating…</small>
                </div>
            </section>

            <section class="panel market-info-card">
                <div class="market-info-icon overlap">⇄</div>
                <div class="market-info-copy">
                    <span>Current Overlap</span>
                    <strong class="market-info-compact" id="marketOverlap">
                        {{ $snapshot['summary']['current_overlap'] ?? 'None' }}
                    </strong>
                    <small>Based on configured trading-centre windows</small>
                </div>
            </section>

            <section class="panel market-insight-card">
                <div class="market-insight-topline">
                    <span class="market-insight-icon">✦</span>
                    <div>
                        <strong>Market Session Insight</strong>
                        <span id="marketStatusText">
                            {{ $snapshot['market_status'] === 'open' ? 'Major trading centres are active' : 'Between configured sessions' }}
                        </span>
                    </div>
                </div>
                <p id="marketInsightText">{{ $snapshot['summary']['insight'] }}</p>
            </section>
        </div>
    </div>

    <div class="market-clock-lower-grid">
        <section class="panel market-session-panel">
            <div class="market-panel-heading">
                <div>
                    <div class="panel-eyebrow">SESSION OVERVIEW</div>
                    <h2>Global Trading Centres</h2>
                </div>
                <span class="market-refresh-note">Auto refresh · 60 sec</span>
            </div>

            <div class="market-table-scroll">
                <table class="market-session-table">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Status</th>
                            <th>Centre Local Time</th>
                            <th>Configured Hours</th>
                            <th>Next Open in View</th>
                        </tr>
                    </thead>
                    <tbody id="marketSessionRows">
                        @foreach($snapshot['sessions'] as $session)
                            <tr>
                                <td>
                                    <strong>{{ $session['name'] }}</strong>
                                    <small>{{ $session['region'] }} · {{ $session['timezone'] }}</small>
                                </td>
                                <td>
                                    <span class="market-status-badge {{ $session['status'] }}">
                                        {{ strtoupper($session['status']) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $session['local_time'] }}
                                    <small>UTC{{ $session['local_offset'] }}</small>
                                </td>
                                <td>
                                    {{ $session['configured_open_local'] }}–{{ $session['configured_close_local'] }}
                                </td>
                                <td>{{ $session['next_open_at_display'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel market-summary-panel">
            <div class="market-panel-heading">
                <div>
                    <div class="panel-eyebrow">MARKET SUMMARY</div>
                    <h2>Live Coverage</h2>
                </div>
            </div>

            <div class="market-summary-donut" id="marketSummaryDonut">
                <div>
                    <strong id="marketDonutValue">
                        {{ $snapshot['summary']['open_now'] }}/{{ $snapshot['summary']['total_sessions'] }}
                    </strong>
                    <span>Open now</span>
                </div>
            </div>

            <dl class="market-summary-list">
                <div>
                    <dt>Total Sessions</dt>
                    <dd id="marketTotalSessions">{{ $snapshot['summary']['total_sessions'] }}</dd>
                </div>
                <div>
                    <dt>Open Now</dt>
                    <dd id="marketOpenNow">{{ $snapshot['summary']['open_now'] }}</dd>
                </div>
                <div>
                    <dt>Closed</dt>
                    <dd id="marketClosedNow">{{ $snapshot['summary']['closed_now'] }}</dd>
                </div>
                <div>
                    <dt>Display Timezone</dt>
                    <dd id="marketDisplayTimezone">{{ $snapshot['display_timezone'] }}</dd>
                </div>
            </dl>

            <div class="market-disclaimer">
                Session windows are a TradeX visual reference. Official exchange holidays,
                instrument-specific hours and broker availability can differ.
            </div>
        </section>
    </div>
</div>

<script type="application/json" id="marketClockInitialData">{!! json_encode(
    $snapshot,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/market-clock.js') }}"></script>
@endpush
