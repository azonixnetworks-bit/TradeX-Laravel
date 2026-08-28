@extends('admin.layouts.app')

@section('title', $dailyTradeIdea->symbol . ' Daily Trade Idea | TradeX')
@section('page-title', 'Daily Trade Ideas')

@push('styles')
<style>
    .dti-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.75fr);gap:20px}.dti-panel{padding:21px}.dti-hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:18px}.dti-hero h2{margin:5px 0;font-size:20px}.dti-hero p{margin:0;font-size:11px;opacity:.58;line-height:1.6}.dti-badges{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}.dti-badge{padding:6px 9px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.dti-badge.buy{color:#77d9a6}.dti-badge.sell{color:#ff8585}.structure-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.metric{padding:12px;border:1px solid rgba(255,255,255,.06);border-radius:10px;background:rgba(255,255,255,.018)}.metric span{display:block;font-size:8px;opacity:.45;text-transform:uppercase;margin-bottom:4px}.metric strong{font-size:11px;word-break:break-word}.section-title{margin:22px 0 10px;font-size:12px;letter-spacing:.04em}.target-list{display:grid;gap:8px}.target{display:flex;justify-content:space-between;gap:12px;padding:11px 12px;border:1px solid rgba(255,255,255,.06);border-radius:10px}.analysis{white-space:pre-wrap;font-size:11px;line-height:1.7;opacity:.7}.action-box{padding:16px;border:1px solid rgba(255,255,255,.07);border-radius:12px;margin-bottom:12px}.action-box h3{margin:0 0 6px;font-size:12px}.action-box p{margin:0 0 12px;font-size:9px;opacity:.5;line-height:1.55}.action-box button,.action-link{display:block;width:100%;box-sizing:border-box;padding:9px 10px;border-radius:9px;text-align:center;text-decoration:none;font:inherit;font-size:10px;font-weight:800;cursor:pointer}.publish{border:1px solid rgba(119,217,166,.24);background:rgba(119,217,166,.07);color:#77d9a6}.archive{border:1px solid rgba(218,178,88,.25);background:rgba(218,178,88,.08);color:#e0ba65}.action-link{border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.035);color:inherit;margin-bottom:8px}.immutable-note{padding:12px;border:1px solid rgba(218,178,88,.14);border-radius:10px;background:rgba(218,178,88,.035);font-size:9px;line-height:1.6;opacity:.72}@media(max-width:980px){.dti-layout{grid-template-columns:1fr}.structure-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:580px){.dti-hero{flex-direction:column}.dti-badges{justify-content:flex-start}.structure-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">DAILY TRADE IDEA · LIFECYCLE</div>
        <h1>{{ $dailyTradeIdea->symbol }} <span>{{ $dailyTradeIdea->direction }}</span></h1>
        <p>{{ $dailyTradeIdea->title }}</p>
    </div>

    <a class="secondary-btn" href="{{ route('admin.daily_trade_ideas.index') }}">← Back to Daily Trade Ideas</a>
</div>

<div class="dti-layout">
    <div>
        <section class="panel dti-panel">
            <div class="dti-hero">
                <div>
                    <div class="panel-eyebrow">
                        {{ $dailyTradeIdea->category }}
                        @if($dailyTradeIdea->market_region)
                            · {{ str_replace('_', ' ', $dailyTradeIdea->market_region) }}
                        @endif
                    </div>
                    <h2>{{ $dailyTradeIdea->title }}</h2>
                    <p>{{ $dailyTradeIdea->summary ?: 'No optional summary added.' }}</p>
                </div>

                <div class="dti-badges">
                    <span class="dti-badge {{ strtolower($dailyTradeIdea->direction) }}">{{ $dailyTradeIdea->direction }}</span>
                    <span class="dti-badge">{{ $dailyTradeIdea->timeframe }}</span>
                    <span class="dti-badge">{{ $dailyTradeIdea->status }}</span>
                </div>
            </div>

            @if($dailyTradeIdea->hasChartImage())
                <h3 class="section-title">CHART</h3>
                <img
                    src="{{ route('admin.daily_trade_ideas.chart_image', ['dailyTradeIdea' => $dailyTradeIdea]) }}"
                    alt="Daily Trade Idea chart"
                    style="display:block;width:100%;max-height:620px;object-fit:contain;border-radius:12px;border:1px solid rgba(255,255,255,.07);background:#080808"
                >
            @endif

            @if($dailyTradeIdea->tradingview_url)
                <a
                    href="{{ $dailyTradeIdea->tradingview_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="action-link"
                    style="margin-top:10px"
                >
                    Open TradingView Chart ↗
                </a>
            @endif

            <h3 class="section-title">TRADE STRUCTURE</h3>
            <div class="structure-grid">
                <div class="metric"><span>Entry Zone</span><strong>{{ $dailyTradeIdea->displayEntryZone() }}</strong></div>
                <div class="metric"><span>Stop Loss</span><strong>{{ $dailyTradeIdea->stop_loss ?? '—' }}</strong></div>
                <div class="metric"><span>Targets</span><strong>{{ $dailyTradeIdea->targets->count() ?: '—' }}</strong></div>
                <div class="metric"><span>Published</span><strong>{{ $dailyTradeIdea->published_at?->format('d M Y H:i') ?? '—' }}</strong></div>
                <div class="metric"><span>Archived</span><strong>{{ $dailyTradeIdea->archived_at?->format('d M Y H:i') ?? '—' }}</strong></div>
                <div class="metric"><span>Created By</span><strong>{{ $dailyTradeIdea->createdByAdmin?->name ?? '—' }}</strong></div>
            </div>

            @if($dailyTradeIdea->targets->isNotEmpty())
                <h3 class="section-title">TAKE PROFIT TARGETS</h3>
                <div class="target-list">
                    @foreach($dailyTradeIdea->targets as $target)
                        <div class="target">
                            <span>TP{{ $loop->iteration }}</span>
                            <strong>{{ $target->price }}</strong>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($dailyTradeIdea->analysis)
                <h3 class="section-title">ANALYSIS / RATIONALE</h3>
                <div class="analysis">{{ $dailyTradeIdea->analysis }}</div>
            @endif
        </section>
    </div>

    <aside>
        @if(($permissions['update'] ?? false) && $dailyTradeIdea->isDraft())
            <section class="panel action-box">
                <h3>Edit Draft</h3>
                <p>Daily Trade Ideas are editable only before publication.</p>
                <a
                    class="action-link"
                    href="{{ route('admin.daily_trade_ideas.edit', ['dailyTradeIdea' => $dailyTradeIdea]) }}"
                >
                    Edit Daily Trade Idea
                </a>
            </section>
        @endif

        @if(($permissions['publish'] ?? false) && $dailyTradeIdea->isDraft())
            <section class="panel action-box">
                <h3>Publish</h3>
                <p>Publishing makes this Daily Trade Idea active in the Admin lifecycle.</p>
                <form method="POST" action="{{ route('admin.daily_trade_ideas.publish', ['dailyTradeIdea' => $dailyTradeIdea]) }}">
                    @csrf
                    @method('PATCH')
                    <button class="publish" type="submit">Publish Daily Trade Idea</button>
                </form>
            </section>
        @endif

        @if(($permissions['archive'] ?? false) && $dailyTradeIdea->isPublished())
            <section class="panel action-box">
                <h3>Archive</h3>
                <p>Archive a Daily Trade Idea after its active daily lifecycle is complete.</p>
                <form method="POST" action="{{ route('admin.daily_trade_ideas.archive', ['dailyTradeIdea' => $dailyTradeIdea]) }}">
                    @csrf
                    @method('PATCH')
                    <button class="archive" type="submit">Archive Daily Trade Idea</button>
                </form>
            </section>
        @endif

        @if($dailyTradeIdea->isArchived())
            <div class="immutable-note">
                <strong>ARCHIVED:</strong> this Daily Trade Idea is read-only. Historical content is preserved for auditability.
            </div>
        @endif
    </aside>
</div>
@endsection
