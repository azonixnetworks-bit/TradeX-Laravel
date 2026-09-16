@extends('admin.layouts.app')

@section('title', 'Economic News Sources | TradeX Administration')
@section('page-title', 'Economic News Intelligence')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/economic-news.css') }}">
@endpush

@section('content')
<div class="economic-news-page">
    <div class="page-header economic-news-header">
        <div>
            <div class="page-eyebrow">ADMIN-29 · NEWS SOURCE MANAGEMENT</div>
            <h1>Economic News <span>Sources</span></h1>
            <p>Manage source identity, priority and reliability metadata. Provider credentials remain outside this table and are never rendered in the admin UI.</p>
        </div>
        <a class="economic-news-link" href="{{ route('admin.economic_news.index') }}">Back to intelligence</a>
    </div>

    @if($errors->any())
        <div class="economic-news-validation"><strong>Source change was not saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="panel economic-news-panel">
        <div class="economic-news-panel-head"><div><div class="panel-eyebrow">NEW SOURCE</div><h2>Add Economic News Source</h2></div></div>
        <form method="POST" action="{{ route('admin.economic_news.sources.store') }}" class="economic-news-form-grid economic-news-inline-form">
            @csrf
            <label class="economic-news-field"><span>Code *</span><input name="code" maxlength="60" required placeholder="FOREX_FACTORY"></label>
            <label class="economic-news-field"><span>Name *</span><input name="name" maxlength="160" required></label>
            <label class="economic-news-field"><span>Type *</span><select name="source_type" required>@foreach(['MANUAL','API','RSS','WEBHOOK'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
            <label class="economic-news-field"><span>Homepage URL</span><input type="url" name="homepage_url" maxlength="500"></label>
            <label class="economic-news-field"><span>Priority *</span><input type="number" name="priority" min="1" max="1000" value="100" required></label>
            <label class="economic-news-field"><span>Reliability weight % *</span><input type="number" name="reliability_weight" min="0" max="100" step="0.01" value="100" required></label>
            <div class="economic-news-field"><span>Status</span><input type="hidden" name="is_enabled" value="0"><label class="economic-news-check"><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label></div>
            <label class="economic-news-field is-wide"><span>Notes</span><textarea name="notes" rows="3" maxlength="5000"></textarea></label>
            <div class="economic-news-field"><span>&nbsp;</span><button class="economic-news-primary" type="submit">Add Source</button></div>
        </form>
    </section>

    <section class="panel economic-news-panel">
        <div class="economic-news-panel-head"><div><div class="panel-eyebrow">REGISTERED</div><h2>Source Registry</h2></div><span class="economic-news-readonly">{{ $sources->count() }} SOURCES</span></div>
        @forelse($sources as $source)
            <form method="POST" action="{{ route('admin.economic_news.sources.update', $source) }}" class="economic-news-source-row">
                @csrf @method('PATCH')
                <div class="economic-news-source-title">
                    <strong>{{ $source->name }}</strong>
                    <span>{{ $source->events_count }} events · {{ $source->updates_count }} updates</span>
                    @if($source->last_error_at)<small>Last error {{ $source->last_error_at->utc()->format('Y-m-d H:i') }} UTC · {{ $source->last_error_code }}</small>@endif
                </div>
                <input name="code" maxlength="60" required value="{{ $source->code }}" aria-label="Source code">
                <input name="name" maxlength="160" required value="{{ $source->name }}" aria-label="Source name">
                <select name="source_type" aria-label="Source type">@foreach(['MANUAL','API','RSS','WEBHOOK'] as $value)<option value="{{ $value }}" @selected($source->source_type === $value)>{{ $value }}</option>@endforeach</select>
                <input type="url" name="homepage_url" maxlength="500" value="{{ $source->homepage_url }}" placeholder="Homepage URL" aria-label="Homepage URL">
                <input type="number" name="priority" min="1" max="1000" value="{{ $source->priority }}" aria-label="Priority">
                <input type="number" name="reliability_weight" min="0" max="100" step="0.01" value="{{ $source->reliability_weight }}" aria-label="Reliability weight">
                <div><input type="hidden" name="is_enabled" value="0"><label class="economic-news-check"><input type="checkbox" name="is_enabled" value="1" @checked($source->is_enabled)> Enabled</label></div>
                <textarea name="notes" rows="2" maxlength="5000" placeholder="Notes" aria-label="Notes">{{ $source->notes }}</textarea>
                <button class="economic-news-primary" type="submit">Save</button>
            </form>
            <div class="economic-news-source-delete">
                <form method="POST" action="{{ route('admin.economic_news.sources.destroy', $source) }}">@csrf @method('DELETE')<button class="economic-news-danger" type="submit" @disabled($source->events_count > 0)>Delete unused source</button></form>
            </div>
        @empty
            <div class="economic-news-empty">No news sources registered yet.</div>
        @endforelse
    </section>
</div>
@endsection
