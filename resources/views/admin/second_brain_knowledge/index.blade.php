@extends('admin.layouts.app')

@section('title', $title . ' | 2ND Brain Knowledge | TradeX Administration')
@section('page-title', '2ND Brain Knowledge')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/second-brain-knowledge.css') }}">
@endpush

@section('content')
@php
    $modules = [
        ['route' => 'admin.second_brain_knowledge.index', 'key' => 'dashboard', 'icon' => '◈', 'label' => 'Knowledge Dashboard'],
        ['route' => 'admin.second_brain_knowledge.subjects', 'key' => 'subjects', 'icon' => '▦', 'label' => 'Subjects'],
        ['route' => 'admin.second_brain_knowledge.library', 'key' => 'library', 'icon' => '▤', 'label' => 'Knowledge Library'],
        ['route' => 'admin.second_brain_knowledge.rules', 'key' => 'rules', 'icon' => '⚙', 'label' => 'Rules'],
        ['route' => 'admin.second_brain_knowledge.examples', 'key' => 'examples', 'icon' => '◎', 'label' => 'Examples'],
        ['route' => 'admin.second_brain_knowledge.media', 'key' => 'media', 'icon' => '▣', 'label' => 'Media Library'],
        ['route' => 'admin.second_brain_knowledge.review', 'key' => 'review', 'icon' => '✓', 'label' => 'AI Extraction Review'],
        ['route' => 'admin.second_brain_knowledge.rag', 'key' => 'rag', 'icon' => '◇', 'label' => 'RAG / Vector Status'],
        ['route' => 'admin.second_brain_knowledge.mcp_sync', 'key' => 'mcp', 'icon' => '⇄', 'label' => 'MCP Knowledge Sync'],
        ['route' => 'admin.second_brain_knowledge.versions', 'key' => 'versions', 'icon' => '◷', 'label' => 'Version History'],
    ];
@endphp

<div class="second-brain-knowledge-page">
    <div class="page-header">
        <div>
            <div class="page-eyebrow">AI TRADING SECOND BRAIN</div>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </div>

        <div class="sbk-header-actions">
            <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.subjects') }}">Manage Subjects</a>
            <a class="primary-btn" href="{{ route('admin.second_brain_knowledge.library.create') }}">+ Add Knowledge</a>
        </div>
    </div>

    <section class="sbk-summary-grid" aria-label="Knowledge metrics">
        <article class="panel sbk-summary-card">
            <span>Subjects</span>
            <strong>{{ number_format($metrics['subjects']) }}</strong>
            <small>Founder-defined knowledge categories</small>
        </article>
        <article class="panel sbk-summary-card">
            <span>Knowledge Items</span>
            <strong>{{ number_format($metrics['items']) }}</strong>
            <small>{{ number_format($metrics['drafts']) }} currently in DRAFT</small>
        </article>
        <article class="panel sbk-summary-card">
            <span>Founder Approved</span>
            <strong>{{ number_format($metrics['approved']) }}</strong>
            <small>Approval is required before future AI activation</small>
        </article>
        <article class="panel sbk-summary-card">
            <span>Source Media</span>
            <strong>{{ number_format($metrics['images'] + $metrics['pdfs'] + $metrics['videos']) }}</strong>
            <small>{{ $metrics['images'] }} images · {{ $metrics['pdfs'] }} PDFs · {{ $metrics['videos'] }} videos</small>
        </article>
    </section>

    <section class="panel sbk-workspace">
        <div class="sbk-workspace-header">
            <div>
                <div class="panel-eyebrow">KNOWLEDGE OPERATING SYSTEM</div>
                <h2>{{ $title }}</h2>
            </div>
            <span class="sbk-stage">FOUNDATION ACTIVE</span>
        </div>

        <div class="sbk-module-grid">
            @foreach($modules as $module)
                <a
                    href="{{ route($module['route']) }}"
                    class="sbk-module-card {{ $section === $module['key'] ? 'active' : '' }}"
                >
                    <span class="sbk-module-icon">{{ $module['icon'] }}</span>
                    <span>
                        <strong>{{ $module['label'] }}</strong>
                        <small>
                            @if(in_array($module['key'], ['dashboard', 'subjects', 'library'], true))
                                Available now
                            @else
                                Reserved for next phase
                            @endif
                        </small>
                    </span>
                    <b>›</b>
                </a>
            @endforeach
        </div>
    </section>

    @if($section === 'dashboard')
        <section class="panel sbk-foundation-note">
            <div class="panel-eyebrow">CURRENT SAFE WORKFLOW</div>
            <h2>Capture first. Approve before AI.</h2>
            <p>
                Add your personal trading knowledge as text, images, PDFs and videos.
                New entries are stored as DRAFT in protected storage. Vector indexing,
                RAG retrieval, MCP synchronization, rule execution and signal generation
                remain disabled until later phases are deliberately activated.
            </p>
        </section>
    @else
        <section class="panel sbk-foundation-note">
            <div class="panel-eyebrow">RESERVED MODULE</div>
            <h2>{{ $title }} is intentionally not automated yet</h2>
            <p>
                The Knowledge Library is the source foundation. This module will be connected
                only after the capture schema and founder-review workflow contain enough
                real examples from your methodology.
            </p>
        </section>
    @endif
</div>
@endsection
