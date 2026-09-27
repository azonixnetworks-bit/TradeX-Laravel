{{-- TradeX Admin — ADMIN-05 REV3 Long Term Trade Ideas --}}

@extends('admin.layouts.app')

@section('title', 'Long Term Trade Ideas | TradeX Administration')
@section('page-title', 'Long Term Trade Ideas')

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
    .ti-list{display:grid;gap:13px;padding:22px;border-top:1px solid rgba(255,255,255,.06)}.ti-card{display:grid;grid-template-columns:110px minmax(0,1fr) 235px 180px;gap:16px;align-items:center;padding:17px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}
    .ti-market{display:grid;place-items:center;min-height:82px;border-radius:12px;background:rgba(218,178,88,.06);border:1px solid rgba(218,178,88,.12);text-align:center}.ti-market strong{display:block;color:#ddb65e;font-size:15px}.ti-market span{font-size:9px;opacity:.55;margin-top:4px}
    .ti-copy h3{margin:0 0 6px;font-size:14px}.ti-copy p{margin:0;font-size:11px;opacity:.55;line-height:1.55}.ti-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.ti-tag{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.ti-tag.buy{color:#77d9a6;border-color:rgba(119,217,166,.22)}.ti-tag.sell{color:#ff8585;border-color:rgba(255,133,133,.22)}
    .ti-structure{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ti-metric{padding:8px 9px;border:1px solid rgba(255,255,255,.06);border-radius:9px}.ti-metric span{display:block;font-size:8px;opacity:.45;text-transform:uppercase;margin-bottom:3px}.ti-metric strong{font-size:10px}
    .ti-actions{display:grid;gap:8px}.ti-link{display:block;padding:9px 10px;border:1px solid rgba(255,255,255,.09);border-radius:9px;background:rgba(255,255,255,.035);color:inherit;text-decoration:none;text-align:center;font-size:10px;font-weight:800}
    .ti-quick-actions{display:flex;gap:5px;flex-wrap:wrap}.ti-quick-btn{appearance:none;border:1px solid rgba(255,255,255,.09);border-radius:8px;background:rgba(255,255,255,.025);color:inherit;padding:6px 8px;font:inherit;font-size:8px;font-weight:800;cursor:pointer;line-height:1}.ti-quick-btn.entry{border-color:rgba(96,165,250,.22);color:#8fc1ff}.ti-quick-btn.tp{border-color:rgba(119,217,166,.24);color:#77d9a6}.ti-quick-btn.sl{border-color:rgba(255,133,133,.24);color:#ff8585}.ti-quick-btn.close{border-color:rgba(218,178,88,.24);color:#ddb65e}
    .ti-quick-panel{display:none;grid-column:1/-1;margin-top:2px;padding:9px;border:1px solid rgba(255,255,255,.07);border-radius:9px;background:rgba(0,0,0,.18)}.ti-quick-panel.is-open{display:grid;gap:7px}.ti-quick-panel form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.ti-quick-panel input,.ti-quick-panel select,.ti-quick-panel textarea{width:100%;box-sizing:border-box;padding:7px 8px;border:1px solid rgba(255,255,255,.08);border-radius:7px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:9px}.ti-quick-panel textarea{grid-column:1/-1;min-height:54px;resize:vertical}.ti-quick-submit{grid-column:1/-1;padding:7px 8px;border:1px solid rgba(218,178,88,.22);border-radius:7px;background:rgba(218,178,88,.08);color:#ddb65e;font:inherit;font-size:9px;font-weight:800;cursor:pointer}
    .empty{padding:45px;text-align:center}.empty strong{display:block;margin-bottom:7px}.empty span{font-size:11px;opacity:.55}
    @media(max-width:1100px){.ti-card{grid-template-columns:100px minmax(0,1fr) 220px}.ti-actions{grid-column:2/-1;grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.ti-card{grid-template-columns:1fr}.ti-market{min-height:70px}.ti-actions{grid-column:auto;grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-05 REV3 · LONG TERM MARKET INTELLIGENCE</div>
        <h1>Long Term Trade <span>Ideas</span></h1>
        <p>
            Global Long Term Trade Ideas with optional structure, chart media and context.
        </p>
    </div>

    @if($permissions['create'] ?? false)
        <div>
            <a
                class="primary-btn"
                href="{{ route('admin.trade_ideas.create') }}"
            >
                + New Long Term Trade Idea
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

@include('admin.trade_ideas.partials.market-filter-bar', [
    'baseRoute' => 'admin.trade_ideas.index',
    'statusOptions' => ['DRAFT','PUBLISHED','CLOSED'],
    'statusSelectId' => 'long-term-status-filter',
    'status' => $status,
    'marketFilter' => $marketFilter,
])


<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">LONG TERM TRADE IDEA REGISTER</div>
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
                    $tradeIdea->isPublished()
                    && (
                        ($permissions['update'] ?? false)
                        || ($permissions['close'] ?? false)
                    )
                )
                    <div class="ti-quick-actions">
                        @if(
                            ($permissions['update'] ?? false)
                            && $tradeIdea->entry_triggered_at === null
                        )
                            <button
                                type="button"
                                class="ti-quick-btn entry"
                                data-ti-quick-toggle="entry-{{ $tradeIdea->id }}"
                            >
                                Entry Trigger
                            </button>
                        @endif

                        @if(
                            ($permissions['update'] ?? false)
                            && $tradeIdea->targets->whereNull('hit_at')->isNotEmpty()
                        )
                            <button
                                type="button"
                                class="ti-quick-btn tp"
                                data-ti-quick-toggle="tp-{{ $tradeIdea->id }}"
                            >
                                TP Hit
                            </button>
                        @endif

                        @if($permissions['close'] ?? false)
                            <button
                                type="button"
                                class="ti-quick-btn sl"
                                data-ti-quick-toggle="sl-{{ $tradeIdea->id }}"
                            >
                                SL Hit
                            </button>

                            <button
                                type="button"
                                class="ti-quick-btn close"
                                data-ti-quick-toggle="close-{{ $tradeIdea->id }}"
                            >
                                Close Trade
                            </button>
                        @endif
                    </div>

                    @if(
                        ($permissions['update'] ?? false)
                        && $tradeIdea->entry_triggered_at === null
                    )
                        <div
                            class="ti-quick-panel"
                            data-ti-quick-panel="entry-{{ $tradeIdea->id }}"
                        >
                            <form
                                method="POST"
                                action="{{ route('admin.trade_ideas.updates.store', ['tradeIdea'=>$tradeIdea]) }}"
                            >
                                @csrf
                                <input type="hidden" name="type" value="ENTRY_TRIGGERED">

                                <input
                                    name="price"
                                    inputmode="decimal"
                                    placeholder="Actual entry price"
                                    required
                                >

                                <input
                                    name="message"
                                    maxlength="5000"
                                    placeholder="Optional note"
                                >

                                <button class="ti-quick-submit" type="submit">
                                    Confirm Entry Trigger
                                </button>
                            </form>
                        </div>
                    @endif

                    @if(
                        ($permissions['update'] ?? false)
                        && $tradeIdea->targets->whereNull('hit_at')->isNotEmpty()
                    )
                        <div
                            class="ti-quick-panel"
                            data-ti-quick-panel="tp-{{ $tradeIdea->id }}"
                        >
                            <form
                                method="POST"
                                action="{{ route('admin.trade_ideas.updates.store', ['tradeIdea'=>$tradeIdea]) }}"
                            >
                                @csrf
                                <input type="hidden" name="type" value="TARGET_HIT">

                                <select name="target_id" required>
                                    <option value="">Select target</option>
                                    @foreach($tradeIdea->targets->whereNull('hit_at') as $target)
                                        <option value="{{ $target->id }}">
                                            {{ $target->label }} · {{ $target->price }}
                                        </option>
                                    @endforeach
                                </select>

                                <input
                                    name="price"
                                    inputmode="decimal"
                                    placeholder="Hit price (optional)"
                                >

                                <button class="ti-quick-submit" type="submit">
                                    Confirm TP Hit
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($permissions['close'] ?? false)
                        <div
                            class="ti-quick-panel"
                            data-ti-quick-panel="sl-{{ $tradeIdea->id }}"
                        >
                            <form
                                method="POST"
                                action="{{ route('admin.trade_ideas.close', ['tradeIdea'=>$tradeIdea]) }}"
                                onsubmit="return confirm('Mark Stop Loss as hit and close this Trade Idea as LOSS?');"
                            >
                                @csrf
                                @method('PATCH')

                                <input type="hidden" name="outcome" value="LOSS">
                                <input type="hidden" name="note" value="Stop loss hit.">

                                <input
                                    name="close_price"
                                    inputmode="decimal"
                                    value="{{ $tradeIdea->current_stop_loss ?? $tradeIdea->stop_loss }}"
                                    placeholder="SL hit price"
                                >

                                <button class="ti-quick-submit" type="submit">
                                    Confirm SL Hit + Close
                                </button>
                            </form>
                        </div>

                        <div
                            class="ti-quick-panel"
                            data-ti-quick-panel="close-{{ $tradeIdea->id }}"
                        >
                            <form
                                method="POST"
                                action="{{ route('admin.trade_ideas.close', ['tradeIdea'=>$tradeIdea]) }}"
                                onsubmit="return confirm('Close this Trade Idea?');"
                            >
                                @csrf
                                @method('PATCH')

                                <select name="outcome" required>
                                    <option value="">Outcome</option>
                                    <option value="WIN">WIN</option>
                                    <option value="LOSS">LOSS</option>
                                    <option value="BREAKEVEN">BREAKEVEN</option>
                                    <option value="CANCELLED">CANCELLED</option>
                                    <option value="EXPIRED">EXPIRED</option>
                                </select>

                                <input
                                    name="close_price"
                                    inputmode="decimal"
                                    placeholder="Close price (optional)"
                                >

                                <textarea
                                    name="note"
                                    maxlength="5000"
                                    placeholder="Close note / reason"
                                    required
                                ></textarea>

                                <button class="ti-quick-submit" type="submit">
                                    Confirm Close Trade
                                </button>
                            </form>
                        </div>
                    @endif
                @endif

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
            <strong>No Long Term Trade Ideas found</strong>
            <span>Create the first draft or adjust the active filters.</span>
        </div>
    @endforelse
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-ti-quick-toggle]');

    if (!trigger) {
        return;
    }

    const key = trigger.getAttribute('data-ti-quick-toggle');
    const card = trigger.closest('.ti-card');

    if (!card || !key) {
        return;
    }

    const panels = card.querySelectorAll('[data-ti-quick-panel]');

    panels.forEach(function (panel) {
        if (panel.getAttribute('data-ti-quick-panel') === key) {
            panel.classList.toggle('is-open');
        } else {
            panel.classList.remove('is-open');
        }
    });
});
</script>
@endpush
