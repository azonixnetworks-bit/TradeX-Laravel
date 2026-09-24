@extends('admin.layouts.app')

@section('title', 'Subjects | 2ND Brain Knowledge')
@section('page-title', '2ND Brain Knowledge')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/second-brain-knowledge.css') }}">
@endpush

@section('content')
<div class="second-brain-knowledge-page">
    <div class="page-header">
        <div>
            <div class="page-eyebrow">KNOWLEDGE STRUCTURE</div>
            <h1>Knowledge <span>Subjects</span></h1>
            <p>Create the subject taxonomy that your personal Second Brain will learn from.</p>
        </div>
        <div class="sbk-header-actions">
            <a class="secondary-btn" href="{{ route('admin.second_brain_knowledge.index') }}">Dashboard</a>
            <a class="primary-btn" href="{{ route('admin.second_brain_knowledge.library.create') }}">+ Add Knowledge</a>
        </div>
    </div>

    <div class="sbk-two-column">
        <section class="panel sbk-form-panel">
            <div class="panel-eyebrow">NEW SUBJECT</div>
            <h2>Add a knowledge subject</h2>
            <p>Use your own methodology names. Generic subjects do not need to be created unless you actually use them.</p>

            <form method="POST" action="{{ route('admin.second_brain_knowledge.subjects.store') }}">
                @csrf

                <div class="form-group">
                    <label for="subject-name">Subject Name</label>
                    <input id="subject-name" type="text" name="name" maxlength="120" value="{{ old('name') }}" required placeholder="Example: Supply & Demand">
                </div>

                <div class="form-group">
                    <label for="subject-market">Market Scope</label>
                    <select id="subject-market" name="market_scope" required>
                        @foreach($marketScopes as $scope)
                            <option value="{{ $scope }}" @selected(old('market_scope', 'COMMON') === $scope)>{{ $scope }}</option>
                        @endforeach
                    </select>
                    <small class="form-help">COMMON = shared concept. Use a specific market when your rule differs there.</small>
                </div>

                <div class="form-group">
                    <label for="subject-description">Description</label>
                    <textarea id="subject-description" name="description" rows="5" maxlength="5000" placeholder="What belongs inside this subject?">{{ old('description') }}</textarea>
                </div>

                <div class="form-group">
                    <label for="subject-order">Sort Order</label>
                    <input id="subject-order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}">
                </div>

                @if($errors->any())
                    <div class="sbk-error-box">
                        <strong>Please review the subject details.</strong>
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="admin-form-actions">
                    <button class="primary-btn" type="submit">Create Subject</button>
                </div>
            </form>
        </section>

        <section class="panel sbk-list-panel">
            <div class="sbk-section-heading">
                <div>
                    <div class="panel-eyebrow">CURRENT TAXONOMY</div>
                    <h2>{{ $subjects->count() }} Subjects</h2>
                </div>
            </div>

            @forelse($subjects as $subject)
                <article class="sbk-subject-row">
                    <div>
                        <strong>{{ $subject->name }}</strong>
                        <p>{{ $subject->description ?: 'No description yet.' }}</p>
                    </div>
                    <div class="sbk-subject-meta">
                        <span class="sbk-badge">{{ $subject->market_scope }}</span>
                        <span>{{ $subject->items_count }} items</span>
                    </div>
                </article>
            @empty
                <div class="sbk-empty-state">
                    <strong>No subjects yet.</strong>
                    <p>Create your first personal methodology subject using the form.</p>
                </div>
            @endforelse
        </section>
    </div>
</div>
@endsection
