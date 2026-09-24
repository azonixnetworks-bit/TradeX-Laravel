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
        <div class="sbk-status-pill"><span></span>FOUNDATION MODE</div>
    </div>

    <section class="sbk-summary-grid" aria-label="Second Brain knowledge foundation status">
        <article class="panel sbk-summary-card"><span>Markets</span><strong>4</strong><small>Forex · Crypto · Deriv · Stocks</small></article>
        <article class="panel sbk-summary-card"><span>Knowledge Lifecycle</span><strong>Review First</strong><small>Only founder-approved knowledge becomes active</small></article>
        <article class="panel sbk-summary-card"><span>RAG / Vector</span><strong>Not Enabled</strong><small>Reserved for the ingestion phase</small></article>
        <article class="panel sbk-summary-card"><span>MCP Sync</span><strong>Not Enabled</strong><small>Reserved for approved knowledge only</small></article>
    </section>

    <section class="panel sbk-workspace">
        <div class="sbk-workspace-header">
            <div>
                <div class="panel-eyebrow">KNOWLEDGE OPERATING SYSTEM</div>
                <h2>{{ $title }}</h2>
            </div>
            <span class="sbk-stage">ADMIN FOUNDATION</span>
        </div>

        <div class="sbk-module-grid">
            @foreach($modules as $module)
                <a href="{{ route($module['route']) }}" class="sbk-module-card {{ $section === $module['key'] ? 'active' : '' }}">
                    <span class="sbk-module-icon">{{ $module['icon'] }}</span>
                    <span><strong>{{ $module['label'] }}</strong><small>{{ $section === $module['key'] ? 'Current module' : 'Open module' }}</small></span>
                    <b>›</b>
                </a>
            @endforeach
        </div>
    </section>

    <section class="panel sbk-foundation-note">
        <div class="panel-eyebrow">CURRENT BUILD SCOPE</div>
        <h2>Knowledge capture before automation</h2>
        <p>
            This branch establishes the administration shell only. Database ingestion,
            PDF/image/video processing, chunking, embeddings, RAG, MCP synchronization,
            rule execution and signal generation are intentionally not active yet.
        </p>
    </section>
</div>
@endsection
