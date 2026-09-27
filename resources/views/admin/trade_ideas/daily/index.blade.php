@extends('admin.layouts.app')

@section('title', 'Daily Trade Ideas | TradeX Administration')
@section('page-title', 'Daily Trade Ideas')

@push('styles')
<style>
    .trade-market-filter-panel{padding:18px 20px;margin-bottom:20px}
    .trade-market-filter-head{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:14px}
    .trade-market-filter-head span{display:block;font-size:9px;font-weight:800;letter-spacing:.08em;color:#ddb65e}
    .trade-market-filter-head strong{display:block;margin-top:4px;font-size:14px}
    .trade-market-clear{font-size:10px;font-weight:800;color:#ddb65e;text-decoration:none}
    .trade-market-buttons{display:flex;gap:8px;flex-wrap:wrap}
    .trade-market-button{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 13px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;text-decoration:none;font-size:10px;font-weight:800;letter-spacing:.02em;transition:.18s ease}
    .trade-market-button:hover{border-color:rgba(218,178,88,.28);background:rgba(218,178,88,.06)}
    .trade-market-button.is-active{border-color:rgba(218,178,88,.42);background:rgba(218,178,88,.12);color:#e5bd64;box-shadow:inset 0 0 0 1px rgba(218,178,88,.06)}
    .trade-status-filter{display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06)}
    .trade-status-filter label{display:block;font-size:9px;font-weight:800;opacity:.55;text-transform:uppercase}
    .trade-status-filter select{min-width:170px;padding:9px 10px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:rgba(255,255,255,.03);color:inherit}
    @media(max-width:760px){.trade-market-button{flex:1 1 calc(50% - 8px)}.trade-status-filter select{flex:1;min-width:0}}
    .dti-list{display:grid;gap:13px;padding:22px;border-top:1px solid rgba(255,255,255,.06)}.dti-card{display:grid;grid-template-columns:110px minmax(0,1fr) 235px 180px;gap:16px;align-items:center;padding:17px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}
    .dti-market{display:grid;place-items:center;min-height:82px;border-radius:12px;background:rgba(218,178,88,.06);border:1px solid rgba(218,178,88,.12);text-align:center}.dti-market strong{display:block;color:#ddb65e;font-size:15px}.dti-market span{font-size:9px;opacity:.55;margin-top:4px}
    .dti-copy h3{margin:0 0 6px;font-size:14px}.dti-copy p{margin:0;font-size:11px;opacity:.55;line-height:1.55}.dti-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.dti-tag{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.dti-tag.buy{color:#77d9a6}.dti-tag.sell{color:#ff8585}
    .dti-structure{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.dti-metric{padding:8px 9px;border:1px solid rgba(255,255,255,.06);border-radius:9px}.dti-metric span{display:block;font-size:8px;opacity:.45;text-transform:uppercase;margin-bottom:3px}.dti-metric strong{font-size:10px}.dti-actions{display:grid;gap:8px}.dti-link{display:block;padding:9px 10px;border:1px solid rgba(255,255,255,.09);border-radius:9px;background:rgba(255,255,255,.035);color:inherit;text-decoration:none;text-align:center;font-size:10px;font-weight:800}.empty{padding:45px;text-align:center}.empty strong{display:block;margin-bottom:7px}.empty span{font-size:11px;opacity:.55}
    @media(max-width:1100px){.dti-card{grid-template-columns:100px minmax(0,1fr) 220px}.dti-actions{grid-column:2/-1;grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.dti-card{grid-template-columns:1fr}.dti-market{min-height:70px}.dti-actions{grid-column:auto;grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">TRADE IDEAS · DAILY</div>
        <h1>Daily <span>Trade Ideas</span></h1>
        <p>Short-horizon ideas with a separate Draft → Published → Archived lifecycle.</p>
    </div>

    @if($permissions['create'] ?? false)
        <a class="primary-btn" href="{{ route('admin.daily_trade_ideas.create') }}">+ New Daily Trade Idea</a>
    @endif
</div>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Total</span><strong>{{ number_format($stats['total']) }}</strong></div>
    <div class="user-stat-card"><span>Draft</span><strong class="text-orange">{{ number_format($stats['draft']) }}</strong></div>
    <div class="user-stat-card"><span>Published</span><strong class="text-green">{{ number_format($stats['published']) }}</strong></div>
    <div class="user-stat-card"><span>Archived</span><strong class="text-gold">{{ number_format($stats['archived']) }}</strong></div>
</div>

@include('admin.trade_ideas.partials.market-filter-bar', [
    'baseRoute' => 'admin.daily_trade_ideas.index',
    'statusOptions' => ['DRAFT','PUBLISHED','ARCHIVED'],
    'statusSelectId' => 'daily-status-filter',
    'status' => $status,
    'marketFilter' => $marketFilter,
])


<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">DAILY TRADE IDEA REGISTER</div>
            <h2>Ideas</h2>
        </div>
    </div>

    @forelse($dailyTradeIdeas as $idea)
        @if($loop->first)<div class="dti-list">@endif
        <article class="dti-card">
            <div class="dti-market">
                <div>
                    <strong>{{ $idea->symbol }}</strong>
                    <span>{{ $idea->category }} · {{ $idea->timeframe }}</span>
                </div>
            </div>

            <div class="dti-copy">
                <h3>{{ $idea->title }}</h3>
                <p>{{ $idea->summary ?: 'No optional summary added.' }}</p>
                <div class="dti-tags">
                    <span class="dti-tag {{ strtolower($idea->direction) }}">{{ $idea->direction }}</span>
                    <span class="dti-tag">{{ $idea->status }}</span>
                    @if($idea->hasChartImage())<span class="dti-tag">CHART</span>@endif
                </div>
            </div>

            <div class="dti-structure">
                <div class="dti-metric"><span>Entry Zone</span><strong>{{ $idea->displayEntryZone() }}</strong></div>
                <div class="dti-metric"><span>Stop Loss</span><strong>{{ $idea->stop_loss ?? '—' }}</strong></div>
                <div class="dti-metric"><span>Targets</span><strong>{{ $idea->targets->count() ?: '—' }}</strong></div>
                <div class="dti-metric"><span>Published</span><strong>{{ $idea->published_at?->format('Y-m-d H:i') ?? '—' }}</strong></div>
            </div>

            <div class="dti-actions">
                <a class="dti-link" href="{{ route('admin.daily_trade_ideas.show', ['dailyTradeIdea' => $idea]) }}">View Details</a>
                @if(($permissions['update'] ?? false) && $idea->isDraft())
                    <a class="dti-link" href="{{ route('admin.daily_trade_ideas.edit', ['dailyTradeIdea' => $idea]) }}">Edit Draft</a>
                @endif
            </div>
        </article>
        @if($loop->last)</div>@endif
    @empty
        <div class="empty">
            <strong>No Daily Trade Ideas found</strong>
            <span>Create the first draft or adjust the filters.</span>
        </div>
    @endforelse
</section>
@endsection
