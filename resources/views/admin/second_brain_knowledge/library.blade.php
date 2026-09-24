@extends('admin.layouts.app')

@section('title', 'Knowledge Library | 2ND Brain Knowledge')
@section('page-title', '2ND Brain Knowledge')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/second-brain-knowledge.css') }}?v=20260924-2">
@endpush

@section('content')
<div class="second-brain-knowledge-page">
    <div class="page-header">
        <div>
            <div class="page-eyebrow">PERSONAL METHODOLOGY VAULT</div>
            <h1>Knowledge <span>Library</span></h1>
            <p>Founder-controlled source material. Draft knowledge is not exposed to RAG, MCP or the decision engine.</p>
        </div>
        <div class="sbk-header-actions">
            <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.subjects') }}">Subjects</a>
            <a class="primary-btn" href="{{ route('admin.second_brain_knowledge.library.create') }}">+ Add Knowledge</a>
        </div>
    </div>

    <section class="panel sbk-filter-panel">
        <form method="GET" action="{{ route('admin.second_brain_knowledge.library') }}" class="sbk-filter-grid">
            <div class="form-group">
                <label for="knowledge-search">Search</label>
                <input id="knowledge-search" type="search" name="q" value="{{ request('q') }}" placeholder="Title or heading">
            </div>

            <div class="form-group">
                <label for="knowledge-market">Market</label>
                <select id="knowledge-market" name="market">
                    <option value="">All Markets</option>
                    @foreach($markets as $market)
                        <option value="{{ $market }}" @selected(request('market') === $market)>{{ $market }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="knowledge-type">Type</label>
                <select id="knowledge-type" name="type">
                    <option value="">All Types</option>
                    @foreach($knowledgeTypes as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ str_replace('_', ' ', $type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="knowledge-status">Status</label>
                <select id="knowledge-status" name="status">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sbk-filter-actions">
                <button class="primary-btn" type="submit">Filter</button>
                <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.library') }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="panel sbk-table-panel">
        <div class="sbk-section-heading">
            <div>
                <div class="panel-eyebrow">SOURCE RECORDS</div>
                <h2>{{ number_format($items->total()) }} Knowledge Items</h2>
            </div>
        </div>

        <div class="sbk-table-wrap">
            <table class="sbk-table">
                <thead>
                    <tr>
                        <th>Knowledge</th>
                        <th>Subject</th>
                        <th>Market</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Media</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>
                                <a class="sbk-title-link" href="{{ route('admin.second_brain_knowledge.library.show', $item) }}">{{ $item->title }}</a>
                                <small>{{ $item->heading ?: 'No heading' }}</small>
                            </td>
                            <td>{{ $item->subject?->name ?? '—' }}</td>
                            <td><span class="sbk-badge">{{ $item->market }}</span></td>
                            <td>{{ str_replace('_', ' ', $item->knowledge_type) }}</td>
                            <td><span class="sbk-status sbk-status-{{ strtolower($item->status) }}">{{ str_replace('_', ' ', $item->status) }}</span></td>
                            <td>{{ $item->media_count }}</td>
                            <td>{{ optional($item->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="sbk-empty-state">
                                    <strong>No knowledge found.</strong>
                                    <p>Add your first note, image, PDF or video.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
            <div class="sbk-pagination">{{ $items->links() }}</div>
        @endif
    </section>
</div>
@endsection
