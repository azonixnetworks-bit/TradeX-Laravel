{{-- TradeX Admin — ADMIN-05 REV3 Trade Ideas --}}

@extends('admin.layouts.app')

@section('title', 'Trade Ideas | TradeX Administration')
@section('page-title', 'Trade Ideas')

@push('styles')
<style>
    .ti-filter{display:flex;gap:9px;flex-wrap:wrap;align-items:end;padding:18px 20px;margin-bottom:20px}.ti-filter label{display:block;font-size:9px;font-weight:800;opacity:.55;margin-bottom:5px}.ti-filter select{min-width:150px;padding:9px 10px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:rgba(255,255,255,.03);color:inherit}
    .ti-list{display:grid;gap:13px;padding:22px;border-top:1px solid rgba(255,255,255,.06)}.ti-card{display:grid;grid-template-columns:110px minmax(0,1fr) 235px 180px;gap:16px;align-items:center;padding:17px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}
    .ti-market{display:grid;place-items:center;min-height:82px;border-radius:12px;background:rgba(218,178,88,.06);border:1px solid rgba(218,178,88,.12);text-align:center}.ti-market strong{display:block;color:#ddb65e;font-size:15px}.ti-market span{font-size:9px;opacity:.55;margin-top:4px}
    .ti-copy h3{margin:0 0 6px;font-size:14px}.ti-copy p{margin:0;font-size:11px;opacity:.55;line-height:1.55}.ti-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.ti-tag{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.ti-tag.buy{color:#77d9a6;border-color:rgba(119,217,166,.22)}.ti-tag.sell{color:#ff8585;border-color:rgba(255,133,133,.22)}
    .ti-structure{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ti-metric{padding:8px 9px;border:1px solid rgba(255,255,255,.06);border-radius:9px}.ti-metric span{display:block;font-size:8px;opacity:.45;text-transform:uppercase;margin-bottom:3px}.ti-metric strong{font-size:10px}
    .ti-actions{display:grid;gap:8px}.ti-link{display:block;padding:9px 10px;border:1px solid rgba(255,255,255,.09);border-radius:9px;background:rgba(255,255,255,.035);color:inherit;text-decoration:none;text-align:center;font-size:10px;font-weight:800}
    .empty{padding:45px;text-align:center}.empty strong{display:block;margin-bottom:7px}.empty span{font-size:11px;opacity:.55}
    @media(max-width:1100px){.ti-card{grid-template-columns:100px minmax(0,1fr) 220px}.ti-actions{grid-column:2/-1;grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.ti-card{grid-template-columns:1fr}.ti-market{min-height:70px}.ti-actions{grid-column:auto;grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-05 REV3 · MARKET INTELLIGENCE</div>
        <h1>Trade <span>Ideas</span></h1>
        <p>
            Global Trade Ideas with optional structure, chart media and context.
        </p>
    </div>

    @if($permissions['create'] ?? false)
        <div>
            <a
                class="primary-btn"
                href="{{ route('admin.trade_ideas.create') }}"
            >
                + New Trade Idea
            </a>
        </div>
    @endif
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Total</span>
        <strong>{{ number_format($stats['total']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Draft</span>
        <strong class="text-orange">{{ number_format($stats['draft']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Open / Published</span>
        <strong class="text-green">{{ number_format($stats['published']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Closed</span>
        <strong class="text-gold">{{ number_format($stats['closed']) }}</strong>
    </div>
</div>

<form
    class="panel ti-filter"
    method="GET"
    action="{{ route('admin.trade_ideas.index') }}"
>
    <div>
        <label for="status">STATUS</label>

        <select
            id="status"
            name="status"
        >
            <option value="">All</option>

            @foreach([
                'DRAFT',
                'PUBLISHED',
                'CLOSED',
            ] as $value)
                <option
                    value="{{ $value }}"
                    {{ $status === $value ? 'selected' : '' }}
                >
                    {{ $value }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="category">CATEGORY</label>

        <select
            id="category"
            name="category"
        >
            <option value="">All</option>

            @foreach([
                'FOREX',
                'CRYPTO',
                'DERIV',
                'STOCKS',
            ] as $value)
                <option
                    value="{{ $value }}"
                    {{ $category === $value ? 'selected' : '' }}
                >
                    {{ $value }}
                </option>
            @endforeach
        </select>
    </div>

    <button
        class="secondary-btn"
        type="submit"
    >
        Filter
    </button>

    <a
        class="secondary-btn"
        href="{{ route('admin.trade_ideas.index') }}"
    >
        Reset
    </a>
</form>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">TRADE IDEA REGISTER</div>
            <h2>Ideas</h2>
        </div>
    </div>

    @forelse($tradeIdeas as $tradeIdea)
        @if($loop->first)
            <div class="ti-list">
        @endif

        @php
            $statusClass = match($tradeIdea->status) {
                'PUBLISHED' => 'status-active',
                'CLOSED' => 'status-blocked',
                default => 'status-pending',
            };
        @endphp

        <article class="ti-card">
            <div class="ti-market">
                <div>
                    <strong>{{ $tradeIdea->symbol }}</strong>
                    <span>{{ $tradeIdea->category }} · {{ $tradeIdea->timeframe }}</span>
                </div>
            </div>

            <div class="ti-copy">
                <h3>{{ $tradeIdea->title }}</h3>

                <p>
                    {{ $tradeIdea->summary ?: 'No optional summary added.' }}
                </p>

                <div class="ti-tags">
                    <span class="ti-tag {{ strtolower($tradeIdea->direction) }}">
                        {{ $tradeIdea->direction }}
                    </span>

                    @if($tradeIdea->market_region)
                        <span class="ti-tag">
                            {{ str_replace('_', ' ', $tradeIdea->market_region) }}
                        </span>
                    @endif

                    @if($tradeIdea->hasChartImage())
                        <span class="ti-tag">CHART IMAGE</span>
                    @endif

                    @if($tradeIdea->tradingview_url)
                        <span class="ti-tag">TRADINGVIEW</span>
                    @endif

                    <span class="user-status {{ $statusClass }}">
                        <span class="user-status-dot"></span>
                        {{ $tradeIdea->status }}
                    </span>
                </div>
            </div>

            <div class="ti-structure">
                <div class="ti-metric">
                    <span>Entry Zone</span>
                    <strong>{{ $tradeIdea->displayEntryZone() }}</strong>
                </div>

                <div class="ti-metric">
                    <span>Initial SL</span>
                    <strong>{{ $tradeIdea->stop_loss ?? '—' }}</strong>
                </div>

                <div class="ti-metric">
                    <span>Targets</span>
                    <strong>{{ $tradeIdea->targets->count() ?: '—' }}</strong>
                </div>

                <div class="ti-metric">
                    <span>Published</span>
                    <strong>{{ $tradeIdea->published_at?->format('Y-m-d H:i') ?? '—' }}</strong>
                </div>
            </div>

            <div class="ti-actions">
                <a
                    class="ti-link"
                    href="{{ route('admin.trade_ideas.show', ['tradeIdea'=>$tradeIdea]) }}"
                >
                    View Details
                </a>

                @if(
                    ($permissions['update'] ?? false)
                    && $tradeIdea->isDraft()
                )
                    <a
                        class="ti-link"
                        href="{{ route('admin.trade_ideas.edit', ['tradeIdea'=>$tradeIdea]) }}"
                    >
                        Edit Draft
                    </a>
                @endif
            </div>
        </article>

        @if($loop->last)
            </div>
        @endif
    @empty
        <div class="empty">
            <strong>No Trade Ideas found</strong>
            <span>Create the first draft or adjust the active filters.</span>
        </div>
    @endforelse
</section>
@endsection
