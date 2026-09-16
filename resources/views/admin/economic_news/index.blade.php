@extends('admin.layouts.app')

@section('title', $page['title'].' '.$page['highlight'].' | TradeX Administration')
@section('page-title', 'Economic News Intelligence')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/economic-news.css') }}">
@endpush

@section('content')
@php
    $admin = auth('admin')->user();
    $canManageEvents = $admin?->isSuperAdmin() || $admin?->hasPermission('economic_news.events.manage');
    $canManageSources = $admin?->isSuperAdmin() || $admin?->hasPermission('economic_news.sources.manage');
@endphp
<div class="economic-news-page">
    <div class="page-header economic-news-header">
        <div>
            <div class="page-eyebrow">{{ $page['eyebrow'] }}</div>
            <h1>{{ $page['title'] }} <span>{{ $page['highlight'] }}</span></h1>
            <p>{{ $page['description'] }}</p>
        </div>
        <div class="economic-news-header-side">
            <div class="economic-news-sync">
                <span>SERVER TIME</span>
                <strong>{{ $generatedAt->utc()->format('Y-m-d H:i:s') }} UTC</strong>
            </div>
            <div class="economic-news-actions">
                @if($canManageEvents)<a class="economic-news-link" href="{{ route('admin.economic_news.events.create') }}">＋ New Event</a>@endif
                @if($canManageSources)<a class="economic-news-link" href="{{ route('admin.economic_news.sources.index') }}">Sources</a>@endif
            </div>
        </div>
    </div>

    <div class="economic-news-tabs" role="navigation" aria-label="Economic News sections">
        <a href="{{ route('admin.economic_news.index') }}" class="{{ $mode === 'overview' ? 'active' : '' }}">Overview</a>
        <a href="{{ route('admin.economic_news.high_impact') }}" class="{{ $mode === 'high' ? 'active' : '' }}">High Impact</a>
        <a href="{{ route('admin.economic_news.medium_impact') }}" class="{{ $mode === 'medium' ? 'active' : '' }}">Medium Impact</a>
        <a href="{{ route('admin.economic_news.live') }}" class="{{ $mode === 'live' ? 'active' : '' }}">Live Updates</a>
        <a href="{{ route('admin.economic_news.calendar') }}" class="{{ $mode === 'calendar' ? 'active' : '' }}">Economic Calendar</a>
    </div>

    <div class="economic-news-stats">
        <div class="economic-news-stat is-high"><span>Upcoming High Impact</span><strong>{{ number_format($stats['high_upcoming']) }}</strong></div>
        <div class="economic-news-stat is-medium"><span>Upcoming Medium Impact</span><strong>{{ number_format($stats['medium_upcoming']) }}</strong></div>
        <div class="economic-news-stat is-live"><span>Live Events</span><strong>{{ number_format($stats['live_events']) }}</strong></div>
        <div class="economic-news-stat"><span>Published Today</span><strong>{{ number_format($stats['published_today']) }}</strong></div>
        <div class="economic-news-stat"><span>Enabled Sources</span><strong>{{ number_format($stats['enabled_sources']) }}</strong></div>
    </div>

    @if($mode === 'live')
        <section class="panel economic-news-panel">
            <div class="economic-news-panel-head">
                <div><div class="panel-eyebrow">LIVE INTELLIGENCE</div><h2>Release & Analysis Timeline</h2></div>
                <span class="economic-news-readonly">{{ $canManageEvents ? 'MANAGE READY' : 'READ VIEW' }}</span>
            </div>

            @if($liveUpdates->count())
                <div class="economic-news-live-list">
                    @foreach($liveUpdates as $update)
                        @php
                            $event = $update->event;
                            $effective = $event?->effectiveIntelligence();
                        @endphp
                        <article class="economic-news-live-item">
                            <div class="economic-news-live-time"><strong>{{ $update->published_at?->utc()->format('H:i:s') }}</strong><span>{{ $update->published_at?->utc()->format('Y-m-d') }} UTC</span></div>
                            <div class="economic-news-live-body">
                                <div class="economic-news-live-meta">
                                    <span class="economic-news-badge">{{ $update->update_type }}</span>
                                    @if($event)<span class="economic-news-impact is-{{ strtolower((string) ($effective['impact'] ?? $event->impact)) }}">{{ $effective['impact'] ?? $event->impact }}</span>@endif
                                    @if($update->source)<span>{{ $update->source->name }}</span>@endif
                                </div>
                                <h3>
                                    @if($event && $canManageEvents)<a href="{{ route('admin.economic_news.events.edit', $event) }}">{{ $event->title }}</a>@else{{ $event?->title ?? 'Economic Event' }}@endif
                                </h3>
                                <p>{{ $update->message }}</p>
                                <div class="economic-news-values">
                                    @if(filled($update->actual))<span>Actual <strong>{{ $update->actual }}</strong></span>@endif
                                    @if(filled($update->forecast))<span>Forecast <strong>{{ $update->forecast }}</strong></span>@endif
                                    @if(filled($update->previous))<span>Previous <strong>{{ $update->previous }}</strong></span>@endif
                                    @if(filled($update->bias))<span>Bias <strong>{{ $update->bias }}</strong></span>@endif
                                    @if($update->confidence_score !== null)<span>Confidence <strong>{{ $update->confidence_score }}%</strong></span>@endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="economic-news-pagination">{{ $liveUpdates->links() }}</div>
            @else
                <div class="economic-news-empty">No live economic-news updates are available yet.</div>
            @endif
        </section>
    @else
        <section class="panel economic-news-panel">
            <div class="economic-news-panel-head">
                <div><div class="panel-eyebrow">{{ $mode === 'calendar' ? 'CALENDAR WINDOW' : 'EVENT INTELLIGENCE' }}</div><h2>{{ $mode === 'calendar' ? 'Economic Event Schedule' : 'Economic Events & Pair Direction' }}</h2></div>
                <span class="economic-news-readonly">{{ $canManageEvents ? 'MANAGE READY' : 'READ VIEW' }}</span>
            </div>

            @if($events && $events->count())
                <div class="economic-news-table-wrap">
                    <table class="economic-news-table">
                        <thead><tr><th>Event / Source</th><th>Schedule</th><th>Impact</th><th>Bias</th><th>Confidence</th><th>Volatility</th><th>Affected Pairs</th><th>Status</th>@if($canManageEvents)<th>Manage</th>@endif</tr></thead>
                        <tbody>
                            @foreach($events as $event)
                                @php($effective = $event->effectiveIntelligence())
                                <tr>
                                    <td><strong>{{ $event->title }}</strong><small>{{ $event->currency ?: ($event->country_code ?: 'GLOBAL') }} · {{ $event->source?->name ?? 'Manual / Internal' }}@if($effective['manual_override']) · ADMIN OVERRIDE @endif</small></td>
                                    <td>{{ $event->scheduled_at_utc?->utc()->format('Y-m-d H:i') }}<small>UTC</small></td>
                                    <td><span class="economic-news-impact is-{{ strtolower($effective['impact']) }}">{{ $effective['impact'] }}</span></td>
                                    <td><span class="economic-news-bias is-{{ strtolower($effective['bias']) }}">{{ $effective['bias'] }}</span></td>
                                    <td><strong>{{ $effective['confidence_score'] }}%</strong><div class="economic-news-confidence"><span style="width: {{ min(100, max(0, $effective['confidence_score'])) }}%"></span></div></td>
                                    <td><span class="economic-news-volatility is-{{ strtolower($effective['volatility']) }}">{{ $effective['volatility'] }}</span></td>
                                    <td><div class="economic-news-pairs">@forelse($event->pairs->take(6) as $pair)<span title="{{ $pair->bias }} · {{ $pair->confidence_score }}%">{{ $pair->symbol }} <b>{{ $pair->bias === 'BULLISH' ? '↑' : ($pair->bias === 'BEARISH' ? '↓' : '–') }}</b></span>@empty<small>No mapping</small>@endforelse @if($event->pairs->count() > 6)<small>+{{ $event->pairs->count() - 6 }} more</small>@endif</div></td>
                                    <td><span class="economic-news-status is-{{ strtolower($effective['status']) }}">{{ $effective['status'] }}</span><small>{{ $event->updates_count }} updates</small></td>
                                    @if($canManageEvents)<td><a class="economic-news-link" href="{{ route('admin.economic_news.events.edit', $event) }}">Edit</a></td>@endif
                                </tr>
                                @if(filled($effective['reason']))<tr class="economic-news-reason-row"><td colspan="{{ $canManageEvents ? 9 : 8 }}"><strong>Reason:</strong> {{ $effective['reason'] }}</td></tr>@endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="economic-news-pagination">{{ $events->links() }}</div>
            @else
                <div class="economic-news-empty">No economic events match this section yet.</div>
            @endif
        </section>

        @if($mode === 'overview' && $liveUpdates->count())
            <section class="panel economic-news-panel economic-news-recent">
                <div class="economic-news-panel-head"><div><div class="panel-eyebrow">LATEST</div><h2>Recent Live Updates</h2></div><a href="{{ route('admin.economic_news.live') }}" class="economic-news-link">View live feed</a></div>
                <div class="economic-news-mini-feed">@foreach($liveUpdates as $update)<div><span>{{ $update->published_at?->utc()->format('M d · H:i') }} UTC</span><strong>{{ $update->event?->title ?? 'Economic Event' }}</strong><p>{{ $update->message }}</p></div>@endforeach</div>
            </section>
        @endif
    @endif

    <div class="economic-news-boundary"><strong>Effective intelligence model</strong><p>Original event intelligence remains stored separately from manual overrides. Consumer APIs will receive effective values with override provenance instead of destructive replacement.</p></div>
</div>
@endsection
