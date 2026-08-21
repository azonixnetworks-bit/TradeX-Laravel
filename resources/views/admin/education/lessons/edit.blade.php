{{-- TradeX Admin — ADMIN-03 Edit Lesson --}}

@extends('admin.layouts.app')

@section('title', 'Edit ' . $lesson->title . ' | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<style>
    .education-form-wrap { max-width:1080px; margin:0 auto; }
    .education-context { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; padding:20px 22px; margin-bottom:22px; }
    .education-context h2 { margin:6px 0; font-size:19px; }
    .education-context p { margin:0; opacity:.6; font-size:12px; line-height:1.6; }
    .education-context-meta { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .education-form-panel { padding:24px; }
    .education-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
    .education-field.full { grid-column:1 / -1; }
    .education-field label { display:block; margin-bottom:7px; font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; opacity:.72; }
    .education-field input, .education-field textarea {
        width:100%; box-sizing:border-box; border:1px solid rgba(255,255,255,.09);
        border-radius:11px; background:rgba(255,255,255,.025); color:inherit; padding:12px 13px; outline:none; font:inherit;
    }
    .education-field textarea { min-height:105px; resize:vertical; line-height:1.55; }
    .education-field textarea.description { min-height:190px; }
    .education-field input:focus, .education-field textarea:focus { border-color:rgba(218,178,88,.62); box-shadow:0 0 0 3px rgba(218,178,88,.07); }
    .education-help { display:block; margin-top:6px; font-size:10px; line-height:1.5; opacity:.5; }
    .education-check { display:flex; gap:10px; align-items:flex-start; padding:13px; border:1px solid rgba(255,255,255,.07); border-radius:11px; background:rgba(255,255,255,.02); }
    .education-check input { width:auto; margin-top:2px; accent-color:#ddb65e; }
    .education-check strong { display:block; font-size:12px; margin-bottom:4px; }
    .education-check span { font-size:10px; opacity:.55; line-height:1.5; }
    .education-errors { margin-bottom:18px; padding:14px 16px; border:1px solid rgba(255,91,91,.22); border-radius:11px; background:rgba(255,91,91,.05); }
    .education-errors strong { display:block; margin-bottom:8px; color:#ff8d8d; }
    .education-errors ul { margin:0; padding-left:18px; font-size:11px; line-height:1.7; opacity:.82; }
    .education-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; padding-top:20px; border-top:1px solid rgba(255,255,255,.06); }
    @media(max-width:760px) {
        .education-context { flex-direction:column; }
        .education-context-meta { justify-content:flex-start; }
        .education-grid { grid-template-columns:1fr; }
        .education-field.full { grid-column:auto; }
        .education-actions { flex-direction:column-reverse; }
        .education-actions a, .education-actions button { width:100%; box-sizing:border-box; text-align:center; }
    }
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-eyebrow">EDUCATION MANAGEMENT</div>
        <h1>Edit <span>Lesson</span></h1>
        <p>Update lesson metadata without crossing the separate publication permission boundary.</p>
    </div>
    <div>
        <a href="{{ route('admin.education.courses.modules.lessons.index', ['course' => $course, 'module' => $module]) }}" class="secondary-btn">← Back to Lessons</a>
    </div>
</div>

<div class="education-form-wrap">
    <section class="panel education-context">
        <div>
            <div class="panel-eyebrow">{{ $course->code }} · {{ $module->code }} · {{ $lesson->code }}</div>
            <h2>{{ $lesson->title }}</h2>
            <p>{{ $module->title }}</p>
        </div>
        <div class="education-context-meta">
            @if($lesson->is_preview)
                <span class="account-badge free">PREVIEW</span>
            @endif
            <span class="user-status {{ $lesson->status === 'PUBLISHED' ? 'status-active' : ($lesson->status === 'ARCHIVED' ? 'status-blocked' : 'status-pending') }}">
                <span class="user-status-dot"></span>{{ $lesson->status }}
            </span>
        </div>
    </section>

    @if($errors->any())
        <div class="education-errors">
            <strong>Please correct the following fields.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.education.courses.modules.lessons.update', ['course' => $course, 'module' => $module, 'lesson' => $lesson]) }}">
        @csrf
        @method('PATCH')

        <section class="panel education-form-panel">
            <div class="panel-header">
                <div>
                    <div class="panel-eyebrow">LESSON METADATA</div>
                    <h2>Lesson Details</h2>
                </div>
            </div>

            <div class="education-grid">
                <div class="education-field">
                    <label for="code">Lesson Code</label>
                    <input id="code" name="code" value="{{ old('code', $lesson->code) }}" maxlength="80" required autocomplete="off">
                    <span class="education-help">Unique inside this module.</span>
                </div>

                <div class="education-field">
                    <label for="slug">Slug</label>
                    <input id="slug" name="slug" value="{{ old('slug', $lesson->slug) }}" maxlength="220" required autocomplete="off">
                    <span class="education-help">Unique inside this module.</span>
                </div>

                <div class="education-field full">
                    <label for="title">Title</label>
                    <input id="title" name="title" value="{{ old('title', $lesson->title) }}" maxlength="200" required>
                </div>

                <div class="education-field full">
                    <label for="heading">Heading</label>
                    <input id="heading" name="heading" value="{{ old('heading', $lesson->heading) }}" maxlength="250">
                </div>

                <div class="education-field full">
                    <label for="short_description">Short Description</label>
                    <textarea id="short_description" name="short_description" maxlength="500">{{ old('short_description', $lesson->short_description) }}</textarea>
                </div>

                <div class="education-field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="description">{{ old('description', $lesson->description) }}</textarea>
                </div>

                <div class="education-field">
                    <label for="sort_order">Display Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="4294967295" value="{{ old('sort_order', $lesson->sort_order) }}">
                </div>

                <div class="education-field">
                    <label>Preview Access</label>
                    <label class="education-check">
                        <input type="hidden" name="is_preview" value="0">
                        <input type="checkbox" name="is_preview" value="1" @checked((bool) old('is_preview', $lesson->is_preview))>
                        <span>
                            <strong>Allow lesson preview</strong>
                            <span>Only marks preview eligibility; secure delivery and entitlement rules remain server-controlled.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="education-actions">
                <a href="{{ route('admin.education.courses.modules.lessons.index', ['course' => $course, 'module' => $module]) }}" class="secondary-btn">Cancel</a>
                <button type="submit" class="primary-btn">Save Lesson</button>
            </div>
        </section>
    </form>
</div>

@endsection
