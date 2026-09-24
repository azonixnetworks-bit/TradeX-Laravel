@extends('admin.layouts.app')

@section('title', 'Add Knowledge | 2ND Brain Knowledge')
@section('page-title', '2ND Brain Knowledge')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/second-brain-knowledge.css') }}?v=20260924-2">
@endpush

@section('content')
<div class="second-brain-knowledge-page">
    <div class="page-header">
        <div>
            <div class="page-eyebrow">TRADING BRAIN EXTRACTION</div>
            <h1>Add <span>Knowledge</span></h1>
            <p>Capture what you actually know and do. Every new item starts as DRAFT and remains isolated from AI decision systems.</p>
        </div>
        <div class="sbk-header-actions">
            <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.library') }}">Back to Library</a>
        </div>
    </div>

    <div class="admin-form-callout gold">
        <div class="admin-form-callout-icon" aria-hidden="true">✦</div>
        <div>
            <strong>Founder source of truth</strong>
            Upload personal notes and chart evidence here. Images, PDFs and videos are stored on the protected private disk.
            RAG, vector indexing and MCP synchronization are not triggered by this form.
        </div>
    </div>

    @if($subjects->isEmpty())
        <div class="sbk-error-box">
            <strong>Create a subject first.</strong>
            <div><a href="{{ route('admin.second_brain_knowledge.subjects') }}">Open Subjects</a> and define where this knowledge belongs.</div>
        </div>
    @endif

    <section class="panel admin-form-panel sbk-create-panel">
        <form
            method="POST"
            action="{{ route('admin.second_brain_knowledge.library.store') }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label for="knowledge-subject">Subject</label>
                    <select id="knowledge-subject" name="subject_id" required>
                        <option value="">Select Subject</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected((string) old('subject_id') === (string) $subject->id)>
                                {{ $subject->name }} · {{ $subject->market_scope }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="knowledge-market">Market</label>
                    <select id="knowledge-market" name="market" required>
                        @foreach($markets as $market)
                            <option value="{{ $market }}" @selected(old('market', 'COMMON') === $market)>{{ $market }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="knowledge-type">Knowledge Type</label>
                    <select id="knowledge-type" name="knowledge_type" required>
                        @foreach($knowledgeTypes as $type)
                            <option value="{{ $type }}" @selected(old('knowledge_type', 'NOTE') === $type)>{{ str_replace('_', ' ', $type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="knowledge-heading">Heading</label>
                    <input id="knowledge-heading" type="text" name="heading" maxlength="160" value="{{ old('heading') }}" placeholder="Example: Valid Demand Zone">
                </div>

                <div class="form-group full-width">
                    <label for="knowledge-title">Title</label>
                    <input id="knowledge-title" type="text" name="title" maxlength="200" value="{{ old('title') }}" required placeholder="Give this knowledge item a precise name">
                </div>

                <div class="form-group full-width">
                    <label>Timeframes</label>
                    <div class="sbk-timeframe-grid">
                        @foreach($timeframes as $timeframe)
                            <label class="sbk-check">
                                <input
                                    type="checkbox"
                                    name="timeframes[]"
                                    value="{{ $timeframe }}"
                                    @checked(in_array($timeframe, (array) old('timeframes', []), true))
                                >
                                <span>{{ $timeframe }}</span>
                            </label>
                        @endforeach
                    </div>
                    <small class="form-help">Leave blank if the knowledge is timeframe-independent.</small>
                </div>

                <div class="form-group full-width">
                    <label for="knowledge-notes">Your Notes</label>
                    <textarea
                        id="knowledge-notes"
                        name="notes"
                        rows="14"
                        maxlength="200000"
                        placeholder="Explain the rule, observation, exception, setup or mistake in your own words..."
                    >{{ old('notes') }}</textarea>
                    <small class="form-help">Notes can be empty only when at least one image, PDF or video is attached.</small>
                </div>

                <div class="form-group full-width">
                    <label for="knowledge-tags">Tags</label>
                    <input
                        id="knowledge-tags"
                        type="text"
                        name="tags"
                        value="{{ is_array(old('tags')) ? implode(', ', old('tags')) : old('tags') }}"
                        placeholder="liquidity, demand, london, invalidation"
                    >
                    <small class="form-help">Comma-separated. Tags help future retrieval but do not become trading rules by themselves.</small>
                </div>
            </div>

            <div class="sbk-upload-grid">
                <div class="sbk-upload-card">
                    <span class="sbk-upload-icon">▧</span>
                    <strong>Chart Images</strong>
                    <p>Valid, invalid, winning, losing and annotated examples.</p>
                    <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
                    <small>Up to 20 files · 15 MB each</small>
                </div>

                <div class="sbk-upload-card">
                    <span class="sbk-upload-icon">PDF</span>
                    <strong>PDF Notes</strong>
                    <p>Original course notes, personal documents and methodology references.</p>
                    <input type="file" name="pdfs[]" accept=".pdf,application/pdf" multiple>
                    <small>Up to 5 files · 50 MB each</small>
                </div>

                <div class="sbk-upload-card">
                    <span class="sbk-upload-icon">▶</span>
                    <strong>Video</strong>
                    <p>Personal chart explanations and trading methodology recordings.</p>
                    <input type="file" name="videos[]" accept=".mp4,.mov,.webm,.m4v,video/mp4,video/quicktime,video/webm" multiple>
                    <small>Up to 3 files · 500 MB each · server limits may be lower</small>
                </div>
            </div>

            @if($errors->any())
                <div class="sbk-error-box">
                    <strong>Please review this knowledge item.</strong>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="sbk-safety-note">
                <span>◆</span>
                <div>
                    <strong>Save status: DRAFT</strong>
                    This creates source knowledge only. Founder approval, structured rule extraction,
                    chunking, embeddings, RAG and MCP sync are separate future actions.
                </div>
            </div>

            <div class="admin-form-actions">
                <button class="primary-btn" type="submit" @disabled($subjects->isEmpty())>Save Knowledge Draft</button>
                <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.library') }}">Cancel</a>
            </div>
        </form>
    </section>
</div>
@endsection
