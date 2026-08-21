{{-- TradeX Admin — ADMIN-05 REV3 Trade Idea Detail --}}

@extends('admin.layouts.app')

@section('title', $tradeIdea->symbol . ' Trade Idea | TradeX')
@section('page-title', 'Trade Ideas')

@push('styles')
<style>
    .ti-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.75fr);gap:20px}.ti-panel{padding:21px}.ti-hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:18px}.ti-hero h2{margin:5px 0;font-size:20px}.ti-hero p{margin:0;font-size:11px;opacity:.58;line-height:1.6}.ti-badges{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}
    .ti-badge{padding:6px 9px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.ti-badge.buy{color:#77d9a6;border-color:rgba(119,217,166,.22)}.ti-badge.sell{color:#ff8585;border-color:rgba(255,133,133,.22)}
    .structure-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.metric{padding:12px;border:1px solid rgba(255,255,255,.06);border-radius:10px;background:rgba(255,255,255,.018)}.metric span{display:block;font-size:8px;opacity:.45;text-transform:uppercase;margin-bottom:4px}.metric strong{font-size:11px;word-break:break-word}
    .section-title{margin:22px 0 10px;font-size:12px;letter-spacing:.04em}.target-list{display:grid;gap:8px}.target{display:flex;justify-content:space-between;gap:12px;padding:11px 12px;border:1px solid rgba(255,255,255,.06);border-radius:10px}.target.hit{border-color:rgba(119,217,166,.22);background:rgba(119,217,166,.04)}.target span{font-size:10px;opacity:.55}.target strong{font-size:11px}.analysis{white-space:pre-wrap;font-size:11px;line-height:1.7;opacity:.7}
    .timeline{display:grid;gap:9px}.timeline-item{padding:12px;border-left:2px solid rgba(218,178,88,.4);background:rgba(255,255,255,.018);border-radius:0 9px 9px 0}.timeline-item strong{font-size:10px}.timeline-item p{margin:5px 0 0;font-size:10px;opacity:.62;line-height:1.55}.timeline-item small{display:block;margin-top:6px;font-size:8px;opacity:.4}
    .action-box{padding:16px;border:1px solid rgba(255,255,255,.07);border-radius:12px;margin-bottom:12px}.action-box h3{margin:0 0 6px;font-size:12px}.action-box p{margin:0 0 12px;font-size:9px;opacity:.5;line-height:1.55}.action-box input,.action-box textarea,.action-box select{width:100%;box-sizing:border-box;margin-bottom:8px;padding:9px 10px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:10px}.action-box textarea{min-height:75px;resize:vertical}.action-box button,.action-link{display:block;width:100%;box-sizing:border-box;padding:9px 10px;border-radius:9px;text-align:center;text-decoration:none;font:inherit;font-size:10px;font-weight:800;cursor:pointer}
    .publish{border:1px solid rgba(218,178,88,.25);background:rgba(218,178,88,.09);color:#e0ba65}.update{border:1px solid rgba(119,217,166,.2);background:rgba(119,217,166,.05);color:#77d9a6}.close{border:1px solid rgba(255,179,71,.22);background:rgba(255,179,71,.06);color:#ffc173}.delete{border:1px solid rgba(255,91,91,.24);background:rgba(255,91,91,.06);color:#ff8585}.action-link{border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.035);color:inherit;margin-bottom:8px}
    .integrity-note{padding:12px;border:1px solid rgba(218,178,88,.14);border-radius:10px;background:rgba(218,178,88,.035);font-size:9px;line-height:1.6;opacity:.72}.optional-empty{padding:12px;border:1px dashed rgba(255,255,255,.08);border-radius:10px;font-size:10px;opacity:.5}
    @media(max-width:980px){.ti-layout{grid-template-columns:1fr}.structure-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:580px){.ti-hero{flex-direction:column}.ti-badges{justify-content:flex-start}.structure-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-05 REV3 · TRADE IDEA LIFECYCLE</div>
        <h1>{{ $tradeIdea->symbol }} <span>{{ $tradeIdea->direction }}</span></h1>
        <p>{{ $tradeIdea->title }}</p>
    </div>

    <div>
        <a
            class="secondary-btn"
            href="{{ route('admin.trade_ideas.index') }}"
        >
            ← Back to Trade Ideas
        </a>
    </div>
</div>

<div class="ti-layout">
    <div>
        <section class="panel ti-panel">
            <div class="ti-hero">
                <div>
                    <div class="panel-eyebrow">
                        {{ $tradeIdea->category }}

                        @if($tradeIdea->market_region)
                            · {{ str_replace('_',' ',$tradeIdea->market_region) }}
                        @endif
                    </div>

                    <h2>{{ $tradeIdea->title }}</h2>

                    <p>
                        {{ $tradeIdea->summary ?: 'No optional summary added.' }}
                    </p>
                </div>

                <div class="ti-badges">
                    <span class="ti-badge {{ strtolower($tradeIdea->direction) }}">
                        {{ $tradeIdea->direction }}
                    </span>

                    <span class="ti-badge">
                        {{ $tradeIdea->timeframe }}
                    </span>

                    <span class="ti-badge">
                        {{ $tradeIdea->status }}
                    </span>
                </div>
            </div>

            @if(
                $tradeIdea->hasChartImage()
                || $tradeIdea->tradingview_url
            )
                <h3 class="section-title">
                    CHART / TRADINGVIEW · OPTIONAL
                </h3>

                @if($tradeIdea->hasChartImage())
                    <img
                        src="{{ route('admin.trade_ideas.chart_image', ['tradeIdea'=>$tradeIdea]) }}"
                        alt="Trade Idea chart"
                        style="display:block;width:100%;max-height:620px;object-fit:contain;border-radius:12px;border:1px solid rgba(255,255,255,.07);background:#080808"
                    >
                @endif

                @if($tradeIdea->tradingview_url)
                    <a
                        href="{{ $tradeIdea->tradingview_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="action-link"
                        style="margin-top:10px"
                    >
                        Open TradingView Chart ↗
                    </a>
                @endif
            @endif

            <h3 class="section-title">
                TRADE STRUCTURE · OPTIONAL
            </h3>

            @if(
                $tradeIdea->entry_price_low !== null
                || $tradeIdea->entry_price_high !== null
                || $tradeIdea->stop_loss !== null
                || $tradeIdea->targets->isNotEmpty()
                || $tradeIdea->entry_price_actual !== null
                || $tradeIdea->current_stop_loss !== null
            )
                <div class="structure-grid">
                    <div class="metric">
                        <span>Entry Zone</span>
                        <strong>{{ $tradeIdea->displayEntryZone() }}</strong>
                    </div>

                    <div class="metric">
                        <span>Initial Stop Loss</span>
                        <strong>{{ $tradeIdea->stop_loss ?? '—' }}</strong>
                    </div>

                    <div class="metric">
                        <span>Current Stop Loss</span>
                        <strong>{{ $tradeIdea->current_stop_loss ?? '—' }}</strong>
                    </div>

                    <div class="metric">
                        <span>Actual Entry</span>
                        <strong>{{ $tradeIdea->entry_price_actual ?? '—' }}</strong>
                    </div>
                </div>

                @if($tradeIdea->targets->isNotEmpty())
                    <div class="target-list" style="margin-top:10px">
                        @foreach($tradeIdea->targets as $target)
                            <div class="target {{ $target->isHit() ? 'hit' : '' }}">
                                <div>
                                    <strong>{{ $target->label }}</strong>
                                    <span>{{ $target->isHit() ? ' · HIT' : ' · OPEN' }}</span>
                                </div>

                                <div>
                                    <strong>{{ $target->price }}</strong>

                                    @if($target->isHit())
                                        <span> @ {{ $target->hit_price }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="optional-empty">
                    No optional Trade Structure was supplied for this idea.
                </div>
            @endif

            <h3 class="section-title">
                CONTEXT · OPTIONAL
            </h3>

            @if($tradeIdea->analysis)
                <div class="analysis">
                    {{ $tradeIdea->analysis }}
                </div>
            @else
                <div class="optional-empty">
                    No optional analysis / rationale was supplied.
                </div>
            @endif

            @if($tradeIdea->isClosed())
                <h3 class="section-title">
                    CLOSE RESULT
                </h3>

                <div class="structure-grid">
                    <div class="metric">
                        <span>Outcome</span>
                        <strong>{{ $tradeIdea->close_outcome }}</strong>
                    </div>

                    <div class="metric">
                        <span>Close Price</span>
                        <strong>{{ $tradeIdea->close_price ?? '—' }}</strong>
                    </div>

                    <div class="metric">
                        <span>Closed At</span>
                        <strong>{{ $tradeIdea->closed_at?->format('Y-m-d H:i') }}</strong>
                    </div>
                </div>

                <div
                    class="analysis"
                    style="margin-top:12px"
                >
                    {{ $tradeIdea->close_note }}
                </div>
            @endif
        </section>

        <section
            class="panel ti-panel"
            style="margin-top:20px"
        >
            <div class="panel-eyebrow">
                IMMUTABLE HISTORY
            </div>

            <h2
                style="margin:5px 0 15px;font-size:16px"
            >
                Timeline
            </h2>

            <div class="timeline">
                @forelse($tradeIdea->updates as $update)
                    <div class="timeline-item">
                        <strong>
                            {{ str_replace('_', ' ', $update->type) }}
                        </strong>

                        <p>
                            {{ $update->message ?: 'No timeline note.' }}
                        </p>

                        <small>
                            {{ $update->created_at?->format('Y-m-d H:i:s') }}

                            @if($update->createdByAdmin)
                                · {{ $update->createdByAdmin->name }}
                            @endif
                        </small>
                    </div>
                @empty
                    <div class="integrity-note">
                        No post-publication timeline updates yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <aside>
        @if($tradeIdea->isDraft())
            @if($permissions['update'] ?? false)
                <a
                    class="action-link"
                    href="{{ route('admin.trade_ideas.edit', ['tradeIdea'=>$tradeIdea]) }}"
                >
                    Edit Draft Setup
                </a>
            @endif

            @if($permissions['publish'] ?? false)
                <div class="action-box">
                    <h3>Publish Trade Idea</h3>

                    <p>
                        Optional structure/context may be empty.
                        Publishing locks the current idea record.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('admin.trade_ideas.publication', ['tradeIdea'=>$tradeIdea]) }}"
                        onsubmit="return confirm('Publish and lock this Trade Idea?');"
                    >
                        @csrf
                        @method('PATCH')

                        <textarea
                            name="reason"
                            maxlength="1000"
                            placeholder="Optional audit reason"
                        ></textarea>

                        <button
                            class="publish"
                            type="submit"
                        >
                            Publish & Lock
                        </button>
                    </form>
                </div>
            @endif

            @if($permissions['delete'] ?? false)
                <div class="action-box">
                    <h3>Delete Draft</h3>
                    <p>Only unpublished drafts can be deleted.</p>

                    <form
                        method="POST"
                        action="{{ route('admin.trade_ideas.destroy', ['tradeIdea'=>$tradeIdea]) }}"
                        onsubmit="return confirm('Delete this draft Trade Idea?');"
                    >
                        @csrf
                        @method('DELETE')

                        <button
                            class="delete"
                            type="submit"
                        >
                            Delete Draft
                        </button>
                    </form>
                </div>
            @endif
        @endif

        @if(
            $tradeIdea->isPublished()
            && ($permissions['update'] ?? false)
        )
            <div class="action-box">
                <h3>Add Timeline Update</h3>

                <p>
                    Live-state updates remain audited after publication.
                </p>

                <form
                    method="POST"
                    action="{{ route('admin.trade_ideas.updates.store', ['tradeIdea'=>$tradeIdea]) }}"
                >
                    @csrf

                    <select
                        name="type"
                        required
                    >
                        <option value="NOTE">Note</option>
                        <option value="ENTRY_TRIGGERED">Entry Triggered</option>
                        <option value="STOP_MOVED">Stop Moved</option>

                        @if($tradeIdea->targets->whereNull('hit_at')->isNotEmpty())
                            <option value="TARGET_HIT">Target Hit</option>
                        @endif
                    </select>

                    <input
                        name="price"
                        inputmode="decimal"
                        placeholder="Price for Entry / Stop"
                    >

                    @if($tradeIdea->targets->whereNull('hit_at')->isNotEmpty())
                        <select name="target_id">
                            <option value="">Select target when Target Hit</option>

                            @foreach($tradeIdea->targets->whereNull('hit_at') as $target)
                                <option value="{{ $target->id }}">
                                    {{ $target->label }} · {{ $target->price }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <textarea
                        name="message"
                        maxlength="5000"
                        placeholder="Timeline note"
                    ></textarea>

                    <button
                        class="update"
                        type="submit"
                    >
                        Add Audited Update
                    </button>
                </form>
            </div>
        @endif

        @if(
            $tradeIdea->isPublished()
            && ($permissions['close'] ?? false)
        )
            <div class="action-box">
                <h3>Close Trade Idea</h3>
                <p>Close is final for this Trade Idea record.</p>

                <form
                    method="POST"
                    action="{{ route('admin.trade_ideas.close', ['tradeIdea'=>$tradeIdea]) }}"
                    onsubmit="return confirm('Close this Trade Idea?');"
                >
                    @csrf
                    @method('PATCH')

                    <select
                        name="outcome"
                        required
                    >
                        <option value="">Select outcome</option>
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
                        placeholder="Required close reason / result note"
                        required
                    ></textarea>

                    <button
                        class="close"
                        type="submit"
                    >
                        Close Trade Idea
                    </button>
                </form>
            </div>
        @endif

        <div class="integrity-note">
            <strong>Global distribution:</strong><br>
            Language filtering and Valid Until are removed.
            ADMIN-05A distribution will use this same published Trade Idea
            for all eligible users.
        </div>
    </aside>
</div>
@endsection
