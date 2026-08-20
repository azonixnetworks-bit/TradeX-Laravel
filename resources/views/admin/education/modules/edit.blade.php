{{-- TradeX Admin — ADMIN-03 Edit Module --}}

@extends('admin.layouts.app')

@section('title', 'Edit ' . $module->title . ' | TradeX Education')

@section('page-title', 'Education')


@push('styles')
<style>
    .module-edit-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 24px;
        align-items: start;
    }

    .module-edit-panel {
        overflow: hidden;
    }

    .module-edit-section {
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .module-edit-section:first-child {
        border-top: 0;
    }

    .module-edit-section h2 {
        margin: 4px 0 7px;
        font-size: 18px;
    }

    .module-edit-section p {
        margin: 0 0 20px;
        max-width: 760px;
        opacity: 0.62;
        line-height: 1.6;
        font-size: 13px;
    }

    .module-edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .module-edit-field {
        min-width: 0;
    }

    .module-edit-field.full {
        grid-column: 1 / -1;
    }

    .module-edit-field label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 700;
    }

    .module-edit-field label small {
        font-size: 11px;
        font-weight: 500;
        opacity: 0.52;
    }

    .module-edit-field input,
    .module-edit-field textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.035);
        color: inherit;
        padding: 12px 14px;
        outline: none;
        font: inherit;
        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }

    .module-edit-field textarea {
        min-height: 145px;
        resize: vertical;
        line-height: 1.65;
    }

    .module-edit-field input:focus,
    .module-edit-field textarea:focus {
        border-color: rgba(218, 178, 88, 0.72);
        box-shadow: 0 0 0 3px rgba(218, 178, 88, 0.09);
        background: rgba(255, 255, 255, 0.055);
    }

    .module-edit-help {
        display: block;
        margin-top: 7px;
        font-size: 12px;
        opacity: 0.54;
        line-height: 1.5;
    }

    .module-edit-error {
        display: block;
        margin-top: 7px;
        font-size: 12px;
        color: #ff7c7c;
        line-height: 1.5;
    }

    .module-edit-side {
        display: grid;
        gap: 18px;
        position: sticky;
        top: 92px;
    }

    .module-edit-side-card {
        padding: 21px;
    }

    .module-edit-side-card h3 {
        margin: 0 0 8px;
        font-size: 15px;
    }

    .module-edit-side-card p {
        margin: 0;
        font-size: 12px;
        opacity: 0.62;
        line-height: 1.65;
    }

    .module-edit-context {
        display: grid;
        gap: 11px;
        margin-top: 18px;
    }

    .module-edit-context-row {
        padding-bottom: 11px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .module-edit-context-row:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .module-edit-context-row span {
        display: block;
        margin-bottom: 4px;
        font-size: 10px;
        opacity: 0.48;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .module-edit-context-row strong,
    .module-edit-context-row code {
        display: block;
        font-size: 12px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .module-edit-warning {
        margin-top: 18px;
        padding: 13px;
        border: 1px solid rgba(255, 179, 71, 0.18);
        border-radius: 11px;
        background: rgba(255, 179, 71, 0.06);
        font-size: 11px;
        line-height: 1.6;
        color: #ffc473;
    }

    .module-edit-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.07);
        background: rgba(255, 255, 255, 0.018);
    }

    .module-edit-error-summary {
        margin-bottom: 20px;
        padding: 16px 18px;
        border: 1px solid rgba(255, 90, 90, 0.24);
        border-radius: 12px;
        background: rgba(255, 90, 90, 0.07);
    }

    .module-edit-error-summary strong {
        display: block;
        margin-bottom: 8px;
        color: #ff8a8a;
    }

    .module-edit-error-summary ul {
        margin: 0;
        padding-left: 18px;
    }

    .module-edit-error-summary li {
        margin: 4px 0;
        font-size: 12px;
        line-height: 1.5;
    }

    @media (max-width: 1050px) {
        .module-edit-shell {
            grid-template-columns: 1fr;
        }

        .module-edit-side {
            position: static;
        }
    }

    @media (max-width: 720px) {
        .module-edit-grid {
            grid-template-columns: 1fr;
        }

        .module-edit-field.full {
            grid-column: auto;
        }

        .module-edit-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .module-edit-actions .primary-btn,
        .module-edit-actions .secondary-btn {
            text-align: center;
        }
    }
</style>
@endpush


@section('content')

<div class="page-header">

    <div>

        <div class="page-eyebrow">
            EDUCATION MANAGEMENT
        </div>

        <h1>
            Edit
            <span>
                Module
            </span>
        </h1>

        <p>
            Update module metadata and curriculum order without crossing the protected publication boundary.
        </p>

    </div>


    <div>

        <a
            href="{{ route(
                'admin.education.courses.modules.index',
                $course
            ) }}"
            class="secondary-btn"
        >
            ← Back to Modules
        </a>

    </div>

</div>


@if($errors->any())

    <div class="module-edit-error-summary">

        <strong>
            Module could not be updated.
        </strong>

        <ul>

            @foreach($errors->all() as $error)

                <li>
                    {{ $error }}
                </li>

            @endforeach

        </ul>

    </div>

@endif


<form
    method="POST"
    action="{{ route(
        'admin.education.courses.modules.update',
        [
            'course' => $course,
            'module' => $module,
        ]
    ) }}"
    autocomplete="off"
>
    @csrf
    @method('PATCH')

    <div class="module-edit-shell">

        <section class="panel module-edit-panel">

            {{-- ============================================================
                 MODULE IDENTITY
            ============================================================ --}}

            <div class="module-edit-section">

                <div class="panel-eyebrow">
                    MODULE IDENTITY
                </div>

                <h2>
                    Core Information
                </h2>

                <p>
                    Update the module title, code and slug while preserving server-controlled ownership and UUID.
                </p>


                <div class="module-edit-grid">

                    <div class="module-edit-field">

                        <label for="module-title">
                            Module Title
                            <small>Required</small>
                        </label>

                        <input
                            id="module-title"
                            type="text"
                            name="title"
                            value="{{ old(
                                'title',
                                $module->title
                            ) }}"
                            maxlength="200"
                            required
                            autofocus
                        >

                        @error('title')
                            <span class="module-edit-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="module-edit-field">

                        <label for="module-code">
                            Module Code
                            <small>Required</small>
                        </label>

                        <input
                            id="module-code"
                            type="text"
                            name="code"
                            value="{{ old(
                                'code',
                                $module->code
                            ) }}"
                            maxlength="80"
                            required
                            spellcheck="false"
                        >

                        <span class="module-edit-help">
                            Unique inside this course. Uppercase letters, numbers, dots, underscores and hyphens only.
                        </span>

                        @error('code')
                            <span class="module-edit-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="module-edit-field full">

                        <label for="module-slug">
                            Module Slug
                            <small>Required</small>
                        </label>

                        <input
                            id="module-slug"
                            type="text"
                            name="slug"
                            value="{{ old(
                                'slug',
                                $module->slug
                            ) }}"
                            maxlength="220"
                            required
                            spellcheck="false"
                        >

                        <span class="module-edit-help">
                            Slug changes are manual on the edit page to avoid accidental curriculum URL changes.
                        </span>

                        @error('slug')
                            <span class="module-edit-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 MODULE CONTENT
            ============================================================ --}}

            <div class="module-edit-section">

                <div class="panel-eyebrow">
                    MODULE CONTENT
                </div>

                <h2>
                    Description
                </h2>

                <p>
                    Maintain the learner-facing module summary and detailed curriculum context.
                </p>


                <div class="module-edit-grid">

                    <div class="module-edit-field full">

                        <label for="module-short-description">
                            Short Description
                            <small>Maximum 500 characters</small>
                        </label>

                        <textarea
                            id="module-short-description"
                            name="short_description"
                            maxlength="500"
                            rows="3"
                        >{{ old(
                            'short_description',
                            $module->short_description
                        ) }}</textarea>

                        @error('short_description')
                            <span class="module-edit-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="module-edit-field full">

                        <label for="module-description">
                            Full Description
                            <small>Optional</small>
                        </label>

                        <textarea
                            id="module-description"
                            name="description"
                            rows="8"
                        >{{ old(
                            'description',
                            $module->description
                        ) }}</textarea>

                        @error('description')
                            <span class="module-edit-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 ORDER
            ============================================================ --}}

            <div class="module-edit-section">

                <div class="panel-eyebrow">
                    CURRICULUM ORDER
                </div>

                <h2>
                    Display Position
                </h2>

                <p>
                    Control where this module appears in the parent course curriculum.
                </p>


                <div class="module-edit-grid">

                    <div class="module-edit-field">

                        <label for="module-sort-order">
                            Display Order
                            <small>Optional</small>
                        </label>

                        <input
                            id="module-sort-order"
                            type="number"
                            name="sort_order"
                            value="{{ old(
                                'sort_order',
                                $module->sort_order
                            ) }}"
                            min="0"
                            max="4294967295"
                            step="1"
                            inputmode="numeric"
                        >

                        <span class="module-edit-help">
                            Lower values appear first.
                        </span>

                        @error('sort_order')
                            <span class="module-edit-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 ACTIONS
            ============================================================ --}}

            <div class="module-edit-actions">

                <a
                    href="{{ route(
                        'admin.education.courses.modules.index',
                        $course
                    ) }}"
                    class="secondary-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="primary-btn"
                >
                    Save Module Changes
                </button>

            </div>

        </section>


        {{-- ============================================================
             SIDEBAR
        ============================================================ --}}

        <aside class="module-edit-side">

            <section class="panel module-edit-side-card">

                <div class="panel-eyebrow">
                    MODULE STATE
                </div>

                <h3>
                    Protected lifecycle
                </h3>

                <p>
                    Normal metadata editing cannot directly change module publication state.
                </p>


                <div class="module-edit-context">

                    <div class="module-edit-context-row">

                        <span>
                            Module ID
                        </span>

                        <strong>
                            #{{ $module->id }}
                        </strong>

                    </div>


                    <div class="module-edit-context-row">

                        <span>
                            UUID
                        </span>

                        <code>
                            {{ $module->uuid }}
                        </code>

                    </div>


                    <div class="module-edit-context-row">

                        <span>
                            Status
                        </span>

                        <strong>
                            {{ $module->status }}
                        </strong>

                    </div>


                    <div class="module-edit-context-row">

                        <span>
                            Published At
                        </span>

                        <strong>
                            {{ $module->published_at
                                ? $module->published_at->format('d M Y H:i:s')
                                : 'Not published'
                            }}
                        </strong>

                    </div>


                    <div class="module-edit-context-row">

                        <span>
                            Lessons
                        </span>

                        <strong>
                            {{ number_format(
                                $module->lessons_count ?? 0
                            ) }}
                        </strong>

                    </div>

                </div>


                <div class="module-edit-warning">
                    Publish / Unpublish is a separate action protected by the dedicated education.publish permission.
                </div>

            </section>


            <section class="panel module-edit-side-card">

                <div class="panel-eyebrow">
                    PARENT COURSE
                </div>

                <h3>
                    {{ $course->title }}
                </h3>

                <p>
                    Module ownership is route-controlled and cannot be changed through this form.
                </p>


                <div class="module-edit-context">

                    <div class="module-edit-context-row">

                        <span>
                            Course ID
                        </span>

                        <strong>
                            #{{ $course->id }}
                        </strong>

                    </div>


                    <div class="module-edit-context-row">

                        <span>
                            Course Code
                        </span>

                        <code>
                            {{ $course->code }}
                        </code>

                    </div>


                    <div class="module-edit-context-row">

                        <span>
                            Language
                        </span>

                        <strong>
                            {{ $course->language_code }}
                        </strong>

                    </div>


                    <div class="module-edit-context-row">

                        <span>
                            Access
                        </span>

                        <strong>
                            {{ $course->access_level }}
                        </strong>

                    </div>

                </div>

            </section>

        </aside>

    </div>

</form>

@endsection


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const codeInput = document.getElementById('module-code');
        const slugInput = document.getElementById('module-slug');

        if (codeInput) {
            codeInput.addEventListener('input', function () {
                const cursorPosition = codeInput.selectionStart;

                codeInput.value = codeInput.value
                    .toUpperCase()
                    .replace(/[^A-Z0-9._-]/g, '');

                if (cursorPosition !== null) {
                    codeInput.setSelectionRange(
                        cursorPosition,
                        cursorPosition
                    );
                }
            });
        }

        if (slugInput) {
            slugInput.addEventListener('input', function () {
                slugInput.value = slugInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9-]/g, '')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            });
        }
    });
</script>
@endpush