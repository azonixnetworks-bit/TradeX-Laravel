{{-- TradeX Admin — ADMIN-03 Create Module --}}

@extends('admin.layouts.app')

@section('title', 'Create Module | TradeX Education')

@section('page-title', 'Education')


@push('styles')
<style>
    .module-form-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 24px;
        align-items: start;
    }

    .module-form-panel {
        overflow: hidden;
    }

    .module-form-section {
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .module-form-section:first-child {
        border-top: 0;
    }

    .module-form-section h2 {
        margin: 4px 0 7px;
        font-size: 18px;
    }

    .module-form-section p {
        margin: 0 0 20px;
        max-width: 760px;
        opacity: 0.62;
        line-height: 1.6;
        font-size: 13px;
    }

    .module-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .module-field {
        min-width: 0;
    }

    .module-field.full {
        grid-column: 1 / -1;
    }

    .module-field label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 700;
    }

    .module-field label small {
        font-size: 11px;
        font-weight: 500;
        opacity: 0.52;
    }

    .module-field input,
    .module-field textarea {
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

    .module-field textarea {
        min-height: 145px;
        resize: vertical;
        line-height: 1.65;
    }

    .module-field input:focus,
    .module-field textarea:focus {
        border-color: rgba(218, 178, 88, 0.72);
        box-shadow: 0 0 0 3px rgba(218, 178, 88, 0.09);
        background: rgba(255, 255, 255, 0.055);
    }

    .module-help {
        display: block;
        margin-top: 7px;
        font-size: 12px;
        opacity: 0.54;
        line-height: 1.5;
    }

    .module-error {
        display: block;
        margin-top: 7px;
        font-size: 12px;
        color: #ff7c7c;
        line-height: 1.5;
    }

    .module-side {
        display: grid;
        gap: 18px;
        position: sticky;
        top: 92px;
    }

    .module-side-card {
        padding: 21px;
    }

    .module-side-card h3 {
        margin: 0 0 8px;
        font-size: 15px;
    }

    .module-side-card p {
        margin: 0;
        font-size: 12px;
        opacity: 0.62;
        line-height: 1.65;
    }

    .module-course-context {
        display: grid;
        gap: 11px;
        margin-top: 18px;
    }

    .module-context-row {
        padding-bottom: 11px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .module-context-row:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .module-context-row span {
        display: block;
        margin-bottom: 4px;
        font-size: 10px;
        opacity: 0.48;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .module-context-row strong,
    .module-context-row code {
        display: block;
        font-size: 12px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .module-workflow {
        display: grid;
        gap: 12px;
        margin-top: 17px;
    }

    .module-workflow-step {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 10px;
        align-items: start;
    }

    .module-workflow-step b {
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(218, 178, 88, 0.09);
        color: #ddb65e;
        font-size: 11px;
    }

    .module-workflow-step strong {
        display: block;
        margin-bottom: 3px;
        font-size: 12px;
    }

    .module-workflow-step span {
        display: block;
        font-size: 11px;
        opacity: 0.55;
        line-height: 1.5;
    }

    .module-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.07);
        background: rgba(255, 255, 255, 0.018);
    }

    .module-error-summary {
        margin-bottom: 20px;
        padding: 16px 18px;
        border: 1px solid rgba(255, 90, 90, 0.24);
        border-radius: 12px;
        background: rgba(255, 90, 90, 0.07);
    }

    .module-error-summary strong {
        display: block;
        margin-bottom: 8px;
        color: #ff8a8a;
    }

    .module-error-summary ul {
        margin: 0;
        padding-left: 18px;
    }

    .module-error-summary li {
        margin: 4px 0;
        font-size: 12px;
        line-height: 1.5;
    }

    @media (max-width: 1050px) {
        .module-form-shell {
            grid-template-columns: 1fr;
        }

        .module-side {
            position: static;
        }
    }

    @media (max-width: 720px) {
        .module-form-grid {
            grid-template-columns: 1fr;
        }

        .module-field.full {
            grid-column: auto;
        }

        .module-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .module-actions .primary-btn,
        .module-actions .secondary-btn {
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
            Create
            <span>
                Module
            </span>
        </h1>

        <p>
            Add a new ordered curriculum module inside the selected TradeX course.
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

    <div class="module-error-summary">

        <strong>
            Module could not be created.
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
        'admin.education.courses.modules.store',
        $course
    ) }}"
    autocomplete="off"
>
    @csrf

    <div class="module-form-shell">

        <section class="panel module-form-panel">

            {{-- ============================================================
                 MODULE IDENTITY
            ============================================================ --}}

            <div class="module-form-section">

                <div class="panel-eyebrow">
                    MODULE IDENTITY
                </div>

                <h2>
                    Core Information
                </h2>

                <p>
                    Define a stable module code and slug unique inside this course.
                </p>


                <div class="module-form-grid">

                    <div class="module-field">

                        <label for="module-title">
                            Module Title
                            <small>Required</small>
                        </label>

                        <input
                            id="module-title"
                            type="text"
                            name="title"
                            value="{{ old('title') }}"
                            maxlength="200"
                            required
                            autofocus
                        >

                        @error('title')
                            <span class="module-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="module-field">

                        <label for="module-code">
                            Module Code
                            <small>Required</small>
                        </label>

                        <input
                            id="module-code"
                            type="text"
                            name="code"
                            value="{{ old('code') }}"
                            maxlength="80"
                            required
                            spellcheck="false"
                            placeholder="SMC-M01"
                        >

                        <span class="module-help">
                            Unique within this course. Uppercase letters, numbers, dots, underscores and hyphens only.
                        </span>

                        @error('code')
                            <span class="module-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="module-field full">

                        <label for="module-slug">
                            Module Slug
                            <small>Required</small>
                        </label>

                        <input
                            id="module-slug"
                            type="text"
                            name="slug"
                            value="{{ old('slug') }}"
                            maxlength="220"
                            required
                            spellcheck="false"
                            placeholder="market-structure-foundations"
                        >

                        <span class="module-help">
                            Lowercase letters, numbers and hyphens only. Unique inside the selected course.
                        </span>

                        @error('slug')
                            <span class="module-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 MODULE DESCRIPTION
            ============================================================ --}}

            <div class="module-form-section">

                <div class="panel-eyebrow">
                    MODULE CONTENT
                </div>

                <h2>
                    Description
                </h2>

                <p>
                    Add concise curriculum context that explains what this module covers.
                </p>


                <div class="module-form-grid">

                    <div class="module-field full">

                        <label for="module-short-description">
                            Short Description
                            <small>Maximum 500 characters</small>
                        </label>

                        <textarea
                            id="module-short-description"
                            name="short_description"
                            maxlength="500"
                            rows="3"
                        >{{ old('short_description') }}</textarea>

                        @error('short_description')
                            <span class="module-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="module-field full">

                        <label for="module-description">
                            Full Description
                            <small>Optional</small>
                        </label>

                        <textarea
                            id="module-description"
                            name="description"
                            rows="8"
                        >{{ old('description') }}</textarea>

                        @error('description')
                            <span class="module-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 DISPLAY ORDER
            ============================================================ --}}

            <div class="module-form-section">

                <div class="panel-eyebrow">
                    CURRICULUM ORDER
                </div>

                <h2>
                    Display Position
                </h2>

                <p>
                    Control the module's order inside the course curriculum.
                </p>


                <div class="module-form-grid">

                    <div class="module-field">

                        <label for="module-sort-order">
                            Display Order
                            <small>Optional</small>
                        </label>

                        <input
                            id="module-sort-order"
                            type="number"
                            name="sort_order"
                            value="{{ old('sort_order', 0) }}"
                            min="0"
                            max="4294967295"
                            step="1"
                            inputmode="numeric"
                        >

                        <span class="module-help">
                            Lower values appear first. You can reorder modules later.
                        </span>

                        @error('sort_order')
                            <span class="module-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 FORM ACTIONS
            ============================================================ --}}

            <div class="module-actions">

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
                    Create Draft Module
                </button>

            </div>

        </section>


        {{-- ============================================================
             RIGHT SIDEBAR
        ============================================================ --}}

        <aside class="module-side">

            <section class="panel module-side-card">

                <div class="panel-eyebrow">
                    PARENT COURSE
                </div>

                <h3>
                    {{ $course->title }}
                </h3>

                <p>
                    This module will be permanently associated with the selected parent course.
                </p>


                <div class="module-course-context">

                    <div class="module-context-row">

                        <span>
                            Course ID
                        </span>

                        <strong>
                            #{{ $course->id }}
                        </strong>

                    </div>


                    <div class="module-context-row">

                        <span>
                            Course Code
                        </span>

                        <code>
                            {{ $course->code }}
                        </code>

                    </div>


                    <div class="module-context-row">

                        <span>
                            Language
                        </span>

                        <strong>
                            {{ $course->language_code }}
                        </strong>

                    </div>


                    <div class="module-context-row">

                        <span>
                            Access
                        </span>

                        <strong>
                            {{ $course->access_level }}
                        </strong>

                    </div>


                    <div class="module-context-row">

                        <span>
                            Course Status
                        </span>

                        <strong>
                            {{ $course->status }}
                        </strong>

                    </div>

                </div>

            </section>


            <section class="panel module-side-card">

                <div class="panel-eyebrow">
                    MODULE WORKFLOW
                </div>

                <h3>
                    Draft-first lifecycle
                </h3>

                <p>
                    Module creation and publication remain separate privileged actions.
                </p>


                <div class="module-workflow">

                    <div class="module-workflow-step">

                        <b>1</b>

                        <div>

                            <strong>
                                Create Module
                            </strong>

                            <span>
                                This form creates the module in Draft state.
                            </span>

                        </div>

                    </div>


                    <div class="module-workflow-step">

                        <b>2</b>

                        <div>

                            <strong>
                                Add Lessons
                            </strong>

                            <span>
                                Lesson management will be connected in the next curriculum phase.
                            </span>

                        </div>

                    </div>


                    <div class="module-workflow-step">

                        <b>3</b>

                        <div>

                            <strong>
                                Publish Separately
                            </strong>

                            <span>
                                Publication requires the dedicated education.publish permission.
                            </span>

                        </div>

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
        const titleInput = document.getElementById('module-title');
        const slugInput = document.getElementById('module-slug');
        const codeInput = document.getElementById('module-code');

        let slugEdited =
            slugInput
            && slugInput.value.trim() !== '';

        if (slugInput) {
            slugInput.addEventListener('input', function () {
                slugEdited = true;

                slugInput.value = slugInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9-]/g, '')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            });
        }

        if (
            titleInput
            && slugInput
        ) {
            titleInput.addEventListener('input', function () {
                if (slugEdited) {
                    return;
                }

                slugInput.value = titleInput.value
                    .toLowerCase()
                    .normalize('NFKD')
                    .replace(/[^\x00-\x7F]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            });
        }

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
    });
</script>
@endpush