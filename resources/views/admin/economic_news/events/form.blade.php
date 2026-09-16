@extends('admin.layouts.app')

@section('title', ($creating ? 'Create Economic Event' : 'Edit Economic Event').' | TradeX Administration')
@section('page-title', 'Economic News Intelligence')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/economic-news.css') }}">
@endpush

@section('content')
@php
    $currentPairs = $event->relationLoaded('pairs') ? $event->pairs->values() : collect();
    $pairRows = max(12, $currentPairs->count() + 3);
    $allSymbols = collect($symbolCatalog)->flatten()->unique()->sort()->values();
@endphp
<div class="economic-news-page">
    <div class="page-header economic-news-header">
        <div>
            <div class="page-eyebrow">ADMIN-29 · ECONOMIC EVENT CRUD</div>
            <h1>{{ $creating ? 'Create' : 'Edit' }} <span>Economic Event</span></h1>
            <p>UTC-normalized event intelligence, publication state and affected-pair direction. All mutations are permission-gated and audited.</p>
        </div>
        <div class="economic-news-actions">
            <a class="economic-news-link" href="{{ route('admin.economic_news.index') }}">Back to intelligence</a>
            @if(auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasPermission('economic_news.sources.manage'))
                <a class="economic-news-link" href="{{ route('admin.economic_news.sources.index') }}">News Sources</a>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="economic-news-validation" role="alert">
            <strong>Please correct the highlighted input.</strong>
            <ul>
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $creating ? route('admin.economic_news.events.store') : route('admin.economic_news.events.update', $event) }}"
        class="economic-news-editor"
    >
        @csrf
        @unless($creating) @method('PATCH') @endunless

        <section class="panel economic-news-panel">
            <div class="economic-news-panel-head">
                <div><div class="panel-eyebrow">EVENT</div><h2>Calendar & Intelligence</h2></div>
                @unless($creating)<span class="economic-news-readonly">UUID {{ $event->uuid }}</span>@endunless
            </div>
            <div class="economic-news-form-grid">
                <label class="economic-news-field is-wide">
                    <span>Event title *</span>
                    <input name="title" maxlength="255" required value="{{ old('title', $event->title) }}">
                </label>
                <label class="economic-news-field">
                    <span>News source</span>
                    <select name="source_uuid">
                        <option value="">Manual / Internal</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->uuid }}" @selected(old('source_uuid', $event->source?->uuid) === $source->uuid)>
                                {{ $source->name }} · {{ $source->code }}{{ $source->is_enabled ? '' : ' · DISABLED' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="economic-news-field">
                    <span>Scheduled at (UTC) *</span>
                    <input type="datetime-local" name="scheduled_at_utc" required value="{{ old('scheduled_at_utc', $event->scheduled_at_utc?->utc()->format('Y-m-d\TH:i')) }}">
                </label>
                <label class="economic-news-field">
                    <span>Category</span>
                    <input name="category" maxlength="100" value="{{ old('category', $event->category) }}" placeholder="CPI, Employment, Central Bank...">
                </label>
                <label class="economic-news-field">
                    <span>Country code</span>
                    <input name="country_code" maxlength="8" value="{{ old('country_code', $event->country_code) }}" placeholder="US">
                </label>
                <label class="economic-news-field">
                    <span>Currency</span>
                    <input name="currency" maxlength="12" value="{{ old('currency', $event->currency) }}" placeholder="USD">
                </label>
                <label class="economic-news-field">
                    <span>Impact *</span>
                    <select name="impact" required>
                        @foreach(['HIGH','MEDIUM','LOW'] as $value)<option value="{{ $value }}" @selected(old('impact', $event->impact) === $value)>{{ $value }}</option>@endforeach
                    </select>
                </label>
                <label class="economic-news-field">
                    <span>Status *</span>
                    <select name="status" required>
                        @foreach(['SCHEDULED','LIVE','RELEASED','REVISED','CANCELLED'] as $value)<option value="{{ $value }}" @selected(old('status', $event->status) === $value)>{{ $value }}</option>@endforeach
                    </select>
                </label>
                <label class="economic-news-field">
                    <span>Bias *</span>
                    <select name="bias" required>
                        @foreach(['BULLISH','BEARISH','NEUTRAL'] as $value)<option value="{{ $value }}" @selected(old('bias', $event->bias) === $value)>{{ $value }}</option>@endforeach
                    </select>
                </label>
                <label class="economic-news-field">
                    <span>Confidence % *</span>
                    <input type="number" min="0" max="100" name="confidence_score" required value="{{ old('confidence_score', $event->confidence_score ?? 0) }}">
                </label>
                <label class="economic-news-field">
                    <span>Volatility *</span>
                    <select name="volatility" required>
                        @foreach(['LOW','MEDIUM','HIGH','EXTREME'] as $value)<option value="{{ $value }}" @selected(old('volatility', $event->volatility) === $value)>{{ $value }}</option>@endforeach
                    </select>
                </label>
                <label class="economic-news-field">
                    <span>Actual</span>
                    <input name="actual" maxlength="120" value="{{ old('actual', $event->actual) }}">
                </label>
                <label class="economic-news-field">
                    <span>Forecast</span>
                    <input name="forecast" maxlength="120" value="{{ old('forecast', $event->forecast) }}">
                </label>
                <label class="economic-news-field">
                    <span>Previous</span>
                    <input name="previous" maxlength="120" value="{{ old('previous', $event->previous) }}">
                </label>
                <label class="economic-news-field">
                    <span>Revised previous</span>
                    <input name="revised_previous" maxlength="120" value="{{ old('revised_previous', $event->revised_previous) }}">
                </label>
                <label class="economic-news-field is-wide">
                    <span>Reason / directional rationale</span>
                    <textarea name="reason" rows="3" maxlength="5000">{{ old('reason', $event->reason) }}</textarea>
                </label>
                <label class="economic-news-field is-wide">
                    <span>Full analysis</span>
                    <textarea name="analysis" rows="6" maxlength="20000">{{ old('analysis', $event->analysis) }}</textarea>
                </label>
                <div class="economic-news-field">
                    <span>Publication</span>
                    @if($canPublish)
                        <input type="hidden" name="is_published" value="0">
                        <label class="economic-news-check"><input type="checkbox" name="is_published" value="1" @checked((bool) old('is_published', $event->is_published))> Publish to consumer feeds</label>
                    @else
                        <input type="hidden" name="is_published" value="{{ $event->is_published ? 1 : 0 }}">
                        <div class="economic-news-lock">{{ $event->is_published ? 'PUBLISHED' : 'UNPUBLISHED' }} · publish permission required</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="panel economic-news-panel">
            <div class="economic-news-panel-head">
                <div><div class="panel-eyebrow">AFFECTED PAIRS</div><h2>Pair Direction Mapping</h2></div>
                <span class="economic-news-readonly">{{ $canManagePairs ? 'EDITABLE' : 'VIEW ONLY' }}</span>
            </div>
            @if($canManagePairs)
                <p class="economic-news-section-note">Forex, Crypto and Deriv symbols are validated against the existing TradeX Screener catalog. Leave unused rows blank.</p>
                <datalist id="economic-news-symbols">@foreach($allSymbols as $symbol)<option value="{{ $symbol }}">@endforeach</datalist>
                <div class="economic-news-pair-editor">
                    <div class="economic-news-pair-head"><span>Market</span><span>Symbol</span><span>Bias</span><span>Confidence</span><span>Volatility</span><span>Primary</span><span>Reason</span></div>
                    @for($i = 0; $i < $pairRows; $i++)
                        @php($pair = $currentPairs->get($i))
                        <div class="economic-news-pair-row">
                            <select name="pairs[{{ $i }}][market]">@foreach(['FOREX','CRYPTO','DERIV','STOCKS'] as $value)<option value="{{ $value }}" @selected(old("pairs.$i.market", $pair?->market ?? 'FOREX') === $value)>{{ $value }}</option>@endforeach</select>
                            <input name="pairs[{{ $i }}][symbol]" list="economic-news-symbols" maxlength="50" value="{{ old("pairs.$i.symbol", $pair?->symbol) }}" placeholder="EURUSD">
                            <select name="pairs[{{ $i }}][bias]">@foreach(['BULLISH','BEARISH','NEUTRAL'] as $value)<option value="{{ $value }}" @selected(old("pairs.$i.bias", $pair?->bias ?? 'NEUTRAL') === $value)>{{ $value }}</option>@endforeach</select>
                            <input type="number" min="0" max="100" name="pairs[{{ $i }}][confidence_score]" value="{{ old("pairs.$i.confidence_score", $pair?->confidence_score ?? 0) }}">
                            <select name="pairs[{{ $i }}][volatility]">@foreach(['LOW','MEDIUM','HIGH','EXTREME'] as $value)<option value="{{ $value }}" @selected(old("pairs.$i.volatility", $pair?->volatility ?? 'MEDIUM') === $value)>{{ $value }}</option>@endforeach</select>
                            <div><input type="hidden" name="pairs[{{ $i }}][is_primary]" value="0"><input type="checkbox" name="pairs[{{ $i }}][is_primary]" value="1" @checked((bool) old("pairs.$i.is_primary", $pair?->is_primary ?? false))></div>
                            <input name="pairs[{{ $i }}][reason]" maxlength="2000" value="{{ old("pairs.$i.reason", $pair?->reason) }}" placeholder="Direction rationale">
                            <input type="hidden" name="pairs[{{ $i }}][sort_order]" value="{{ old("pairs.$i.sort_order", $pair?->sort_order ?? (($i + 1) * 10)) }}">
                        </div>
                    @endfor
                </div>
            @else
                <div class="economic-news-existing-pairs">
                    @forelse($currentPairs as $pair)<span>{{ $pair->market }} · {{ $pair->symbol }} · {{ $pair->bias }} · {{ $pair->confidence_score }}%</span>@empty<span>No affected-pair mapping.</span>@endforelse
                </div>
            @endif
        </section>

        <div class="economic-news-submit-row">
            <button type="submit" class="economic-news-primary">{{ $creating ? 'Create Economic Event' : 'Save Event & Mapping' }}</button>
        </div>
    </form>

    @unless($creating)
        @if($canLiveUpdate)
            <section class="panel economic-news-panel">
                <div class="economic-news-panel-head"><div><div class="panel-eyebrow">LIVE</div><h2>Publish Event Update</h2></div></div>
                <form method="POST" action="{{ route('admin.economic_news.events.updates.store', $event) }}" class="economic-news-form-grid economic-news-inline-form">
                    @csrf
                    <label class="economic-news-field"><span>Update type *</span><select name="update_type" required>@foreach(['PRE_EVENT','LIVE','RELEASE','REVISION','ANALYSIS'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
                    <label class="economic-news-field"><span>Bias</span><select name="bias"><option value="">No change</option>@foreach(['BULLISH','BEARISH','NEUTRAL'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
                    <label class="economic-news-field"><span>Confidence %</span><input type="number" min="0" max="100" name="confidence_score"></label>
                    <label class="economic-news-field"><span>Volatility</span><select name="volatility"><option value="">No change</option>@foreach(['LOW','MEDIUM','HIGH','EXTREME'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
                    <label class="economic-news-field"><span>Actual</span><input name="actual" maxlength="120"></label>
                    <label class="economic-news-field"><span>Forecast</span><input name="forecast" maxlength="120"></label>
                    <label class="economic-news-field"><span>Previous</span><input name="previous" maxlength="120"></label>
                    <label class="economic-news-field is-wide"><span>Live message *</span><textarea name="message" rows="4" maxlength="10000" required></textarea></label>
                    <div class="economic-news-field"><span>Visibility</span><input type="hidden" name="is_published" value="0"><label class="economic-news-check"><input type="checkbox" name="is_published" value="1" checked> Publish update</label></div>
                    <div class="economic-news-field"><span>&nbsp;</span><button class="economic-news-primary" type="submit">Record Live Update</button></div>
                </form>
            </section>
        @endif

        @if($canOverride)
            <section class="panel economic-news-panel">
                <div class="economic-news-panel-head">
                    <div><div class="panel-eyebrow">MANUAL ADMIN OVERRIDE</div><h2>Effective Intelligence Override</h2></div>
                    @if($event->activeOverride)<span class="economic-news-impact is-high">ACTIVE</span>@endif
                </div>
                <p class="economic-news-section-note">A new override revokes the previous active override but preserves its history. Blank fields inherit the underlying event intelligence.</p>
                <form method="POST" action="{{ route('admin.economic_news.events.overrides.store', $event) }}" class="economic-news-form-grid economic-news-inline-form">
                    @csrf
                    <label class="economic-news-field"><span>Impact</span><select name="impact"><option value="">Inherit</option>@foreach(['HIGH','MEDIUM','LOW'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
                    <label class="economic-news-field"><span>Status</span><select name="status"><option value="">Inherit</option>@foreach(['SCHEDULED','LIVE','RELEASED','REVISED','CANCELLED'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
                    <label class="economic-news-field"><span>Bias</span><select name="bias"><option value="">Inherit</option>@foreach(['BULLISH','BEARISH','NEUTRAL'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
                    <label class="economic-news-field"><span>Confidence %</span><input type="number" min="0" max="100" name="confidence_score"></label>
                    <label class="economic-news-field"><span>Volatility</span><select name="volatility"><option value="">Inherit</option>@foreach(['LOW','MEDIUM','HIGH','EXTREME'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
                    <label class="economic-news-field"><span>Expires at (UTC)</span><input type="datetime-local" name="expires_at"></label>
                    <label class="economic-news-field is-wide"><span>Override reason</span><textarea name="reason" rows="3" maxlength="5000"></textarea></label>
                    <label class="economic-news-field is-wide"><span>Override analysis</span><textarea name="analysis" rows="4" maxlength="20000"></textarea></label>
                    <label class="economic-news-field is-wide"><span>Audit note *</span><textarea name="override_note" rows="3" maxlength="5000" required placeholder="Why this admin override is necessary"></textarea></label>
                    <div class="economic-news-field"><span>&nbsp;</span><button class="economic-news-primary" type="submit">Activate Override</button></div>
                </form>

                @if($event->activeOverride)
                    <form method="POST" action="{{ route('admin.economic_news.events.overrides.revoke', [$event, $event->activeOverride]) }}" class="economic-news-danger-row">
                        @csrf @method('PATCH')
                        <span>Active override by {{ $event->activeOverride->overriddenByAdmin?->name ?? 'Administrator' }} · {{ $event->activeOverride->created_at?->format('Y-m-d H:i') }} UTC</span>
                        <button type="submit" class="economic-news-danger">Revoke Active Override</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="panel economic-news-panel">
            <div class="economic-news-panel-head"><div><div class="panel-eyebrow">HISTORY</div><h2>Updates & Override Evidence</h2></div></div>
            <div class="economic-news-history-grid">
                <div><h3>Recent live updates</h3>@forelse($event->updates as $update)<p><strong>{{ $update->update_type }}</strong> · {{ $update->published_at?->utc()->format('Y-m-d H:i') }} UTC<br>{{ $update->message }}</p>@empty<p>No live updates recorded.</p>@endforelse</div>
                <div><h3>Override history</h3>@forelse($event->overrides->take(20) as $override)<p><strong>{{ $override->is_active ? 'ACTIVE' : 'INACTIVE' }}</strong> · {{ $override->created_at?->format('Y-m-d H:i') }} UTC<br>{{ $override->override_note }}</p>@empty<p>No manual overrides recorded.</p>@endforelse</div>
            </div>
        </section>

        <section class="economic-news-delete-zone">
            <strong>Draft deletion guard</strong>
            <p>Only events that have never been published and have no live-update or override history may be deleted.</p>
            <form method="POST" action="{{ route('admin.economic_news.events.destroy', $event) }}">@csrf @method('DELETE')<button type="submit" class="economic-news-danger">Delete Eligible Draft</button></form>
        </section>
    @endunless
</div>
@endsection
