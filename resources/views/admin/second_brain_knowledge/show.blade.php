@extends('admin.layouts.app')

@section('title', $item->title . ' | 2ND Brain Knowledge')
@section('page-title', '2ND Brain Knowledge')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/second-brain-knowledge.css') }}">
@endpush

@section('content')
<div class="second-brain-knowledge-page">
    <div class="page-header">
        <div>
            <div class="page-eyebrow">KNOWLEDGE SOURCE RECORD</div>
            <h1>{{ $item->title }}</h1>
            <p>{{ $item->heading ?: 'Founder source knowledge captured for future structured extraction.' }}</p>
        </div>
        <div class="sbk-header-actions">
            <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.library') }}">Back to Library</a>
            <a class="primary-btn" href="{{ route('admin.second_brain_knowledge.library.create') }}">+ Add Knowledge</a>
        </div>
    </div>

    <section class="sbk-detail-grid">
        <article class="panel sbk-detail-main">
            <div class="sbk-detail-topline">
                <span class="sbk-status sbk-status-{{ strtolower($item->status) }}">{{ str_replace('_', ' ', $item->status) }}</span>
                <span>Version {{ $item->version }}</span>
            </div>

            <div class="panel-eyebrow">RAW FOUNDER NOTES</div>
            <div class="sbk-notes">{{ $item->notes ?: 'No text notes were supplied. Review the attached source media.' }}</div>

            @if(!empty($item->tags))
                <div class="sbk-tag-list">
                    @foreach($item->tags as $tag)
                        <span>#{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </article>

        <aside class="panel sbk-detail-side">
            <div class="panel-eyebrow">SOURCE METADATA</div>
            <dl class="sbk-meta-list">
                <div><dt>Subject</dt><dd>{{ $item->subject?->name ?? '—' }}</dd></div>
                <div><dt>Market</dt><dd>{{ $item->market }}</dd></div>
                <div><dt>Type</dt><dd>{{ str_replace('_', ' ', $item->knowledge_type) }}</dd></div>
                <div><dt>Timeframes</dt><dd>{{ implode(', ', $item->timeframes ?? []) ?: 'Any / Not specified' }}</dd></div>
                <div><dt>Created By</dt><dd>{{ $item->createdByAdmin?->name ?? 'Admin' }}</dd></div>
                <div><dt>Created</dt><dd>{{ optional($item->created_at)->format('Y-m-d H:i') }}</dd></div>
                <div><dt>Founder Approval</dt><dd>{{ $item->founder_approved_at ? $item->founder_approved_at->format('Y-m-d H:i') : 'Not approved' }}</dd></div>
            </dl>
        </aside>
    </section>

    <section class="panel sbk-media-panel">
        <div class="sbk-section-heading">
            <div>
                <div class="panel-eyebrow">PROTECTED SOURCE MEDIA</div>
                <h2>{{ $item->media->count() }} Attachments</h2>
            </div>
            <span class="sbk-stage">PRIVATE STORAGE</span>
        </div>

        <div class="sbk-media-grid">
            @forelse($item->media as $media)
                <article class="sbk-media-card">
                    <span class="sbk-upload-icon">
                        {{ $media->media_type === 'PDF' ? 'PDF' : ($media->media_type === 'VIDEO' ? '▶' : '▧') }}
                    </span>
                    <div>
                        <strong>{{ $media->original_name }}</strong>
                        <small>{{ $media->media_type }} · {{ number_format($media->size_bytes / 1024 / 1024, 2) }} MB</small>
                        <small>SHA-256: {{ substr($media->sha256, 0, 16) }}…</small>
                    </div>
                    <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.media.download', $media) }}">Download</a>
                </article>
            @empty
                <div class="sbk-empty-state">
                    <strong>No attachments.</strong>
                    <p>This knowledge item contains text notes only.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="panel sbk-foundation-note">
        <div class="panel-eyebrow">AI BOUNDARY</div>
        <h2>This source record is not an AI-approved trading rule</h2>
        <p>
            DRAFT knowledge remains raw. Later phases will extract Definition, Purpose, Identification,
            Rules, Confirmation, Invalidation, Exceptions, Timeframe Behaviour, Market Differences,
            Examples, Counter-examples and Decision Impact for founder review.
        </p>
    </section>
</div>
@endsection
