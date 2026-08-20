{{-- TradeX Admin — ADMIN-03 Edit Course --}}

@extends('admin.layouts.app')

@section('title', 'Edit ' . $course->title . ' | TradeX Education')

@section('page-title', 'Education')


@push('styles')
<style>
    .education-form-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 24px;
        align-items: start;
    }

    .education-form-panel {
        overflow: hidden;
    }

    .education-form-section {
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .education-form-section:first-child {
        border-top: 0;
    }

    .education-form-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }

    .education-form-section-head h2 {
        margin: 0;
        font-size: 18px;
    }

    .education-form-section-head p {
        margin: 6px 0 0;
        max-width: 720px;
        opacity: 0.68;
        line-height: 1.6;
    }

    .education-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .education-field {
        min-width: 0;
    }

    .education-field.full {
        grid-column: 1 / -1;
    }

    .education-field label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .education-field label small {
        font-size: 11px;
        font-weight: 500;
        opacity: 0.55;
    }

    .education-field input,
    .education-field select,
    .education-field textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.035);
        color: inherit;
        padding: 12px 14px;
        outline: none;
        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }

    .education-field textarea {
        min-height: 150px;
        resize: vertical;
        line-height: 1.65;
    }

    .education-field input:focus,
    .education-field select:focus,
    .education-field textarea:focus {
        border-color: rgba(218, 178, 88, 0.72);
        box-shadow: 0 0 0 3px rgba(218, 178, 88, 0.10);
        background: rgba(255, 255, 255, 0.055);
    }

    .education-field .field-help {
        display: block;
        margin-top: 7px;
        font-size: 12px;
        line-height: 1.5;
        opacity: 0.56;
    }

    .education-field .field-error {
        display: block;
        margin-top: 7px;
        font-size: 12px;
        line-height: 1.5;
        color: #ff7b7b;
    }

    .education-checkbox {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.025);
    }

    .education-checkbox input[type="checkbox"] {
        width: auto;
        margin-top: 3px;
        accent-color: #d9b15c;
    }

    .education-checkbox strong {
        display: block;
        margin-bottom: 4px;
    }

    .education-checkbox span {
        display: block;
        font-size: 12px;
        opacity: 0.62;
        line-height: 1.55;
    }

    .education-side-card {
        position: sticky;
        top: 92px;
        padding: 22px;
    }

    .education-side-card h3 {
        margin: 0 0 10px;
        font-size: 16px;
    }

    .education-side-card p {
        margin: 0;
        opacity: 0.65;
        line-height: 1.65;
        font-size: 13px;
    }

    .education-state-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
        padding: 7px 10px;
        border: 1px solid rgba(218, 178, 88, 0.24);
        border-radius: 999px;
        background: rgba(218, 178, 88, 0.08);
        color: #ddb65e;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
    }

    .education-side-list {
        display: grid;
        gap: 12px;
        margin-top: 20px;
    }

    .education-side-item {
        padding: 12px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .education-side-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .education-side-item span {
        display: block;
        margin-bottom: 4px;
        font-size: 10px;
        opacity: 0.48;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .education-side-item strong,
    .education-side-item code {
        display: block;
        font-size: 12px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }

    .education-warning {
        margin-top: 18px;
        padding: 13px;
        border: 1px solid rgba(255, 179, 71, 0.18);
        border-radius: 11px;
        background: rgba(255, 179, 71, 0.06);
        font-size: 11px;
        line-height: 1.6;
        color: #ffc473;
    }

    .education-form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.07);
        background: rgba(255, 255, 255, 0.018);
    }

    .education-error-summary {
        margin-bottom: 20px;
        padding: 16px 18px;
        border: 1px solid rgba(255, 90, 90, 0.24);
        border-radius: 12px;
        background: rgba(255, 90, 90, 0.07);
    }

    .education-error-summary strong {
        display: block;
        margin-bottom: 8px;
        color: #ff8a8a;
    }

    .education-error-summary ul {
        margin: 0;
        padding-left: 18px;
    }

    .education-error-summary li {
        margin: 4px 0;
        font-size: 12px;
        line-height: 1.5;
    }

    @media (max-width: 1050px) {
        .education-form-shell {
            grid-template-columns: 1fr;
        }

        .education-side-card {
            position: static;
        }
    }

    @media (max-width: 720px) {
        .education-form-grid {
            grid-template-columns: 1fr;
        }

        .education-field.full {
            grid-column: auto;
        }

        .education-form-actions {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .education-form-actions .primary-btn,
        .education-form-actions .secondary-btn {
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
                Course
            </span>
        </h1>

        <p>
            Update course metadata and catalogue settings without crossing the protected publication boundary.
        </p>

    </div>


    <div>

        <a
            href="{{ route(
                'admin.education.courses.show',
                $course
            ) }}"
            class="secondary-btn"
        >
            ← Back to Course
        </a>

    </div>

</div>


@if($errors->any())

    <div class="education-error-summary">

        <strong>
            Course could not be updated.
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
        'admin.education.courses.update',
        $course
    ) }}"
    autocomplete="off"
>
    @csrf
    @method('PATCH')

    <div class="education-form-shell">

        <section class="panel education-form-panel">

            {{-- ============================================================
                 COURSE IDENTITY
            ============================================================ --}}

            <div class="education-form-section">

                <div class="education-form-section-head">

                    <div>

                        <div class="panel-eyebrow">
                            COURSE IDENTITY
                        </div>

                        <h2>
                            Core Information
                        </h2>

                        <p>
                            Update the human-readable course metadata while keeping the server-generated UUID immutable.
                        </p>

                    </div>

                </div>


                <div class="education-form-grid">

                    <div class="education-field">

                        <label for="course-title">
                            Course Title
                            <small>Required</small>
                        </label>

                        <input
                            id="course-title"
                            type="text"
                            name="title"
                            value="{{ old(
                                'title',
                                $course->title
                            ) }}"
                            maxlength="200"
                            required
                            autofocus
                        >

                        @error('title')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="education-field">

                        <label for="course-code">
                            Course Code
                            <small>Required</small>
                        </label>

                        <input
                            id="course-code"
                            type="text"
                            name="code"
                            value="{{ old(
                                'code',
                                $course->code
                            ) }}"
                            maxlength="80"
                            required
                            spellcheck="false"
                        >

                        <span class="field-help">
                            Stable course code. Uppercase letters, numbers, dots, underscores and hyphens only.
                        </span>

                        @error('code')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="education-field">

                        <label for="course-slug">
                            URL Slug
                            <small>Required</small>
                        </label>

                        <input
                            id="course-slug"
                            type="text"
                            name="slug"
                            value="{{ old(
                                'slug',
                                $course->slug
                            ) }}"
                            maxlength="220"
                            required
                            spellcheck="false"
                        >

                        <span class="field-help">
                            Changing the slug changes the readable course identifier. Existing app/API references should use the immutable UUID or database relation.
                        </span>

                        @error('slug')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="education-field">

                        <label for="course-language">
                            Language Code
                            <small>Required</small>
                        </label>

                        <input
                            id="course-language"
                            type="text"
                            name="language_code"
                            value="{{ old(
                                'language_code',
                                $course->language_code
                            ) }}"
                            maxlength="10"
                            required
                            list="course-language-options"
                            spellcheck="false"
                        >

                        <datalist id="course-language-options">
                            <option value="ta">Tamil</option>
                            <option value="en">English</option>
                            <option value="si">Sinhala</option>
                            <option value="hi">Hindi</option>
                            <option value="ml">Malayalam</option>
                            <option value="te">Telugu</option>
                            <option value="ur">Urdu</option>
                        </datalist>

                        @error('language_code')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 DESCRIPTION
            ============================================================ --}}

            <div class="education-form-section">

                <div class="education-form-section-head">

                    <div>

                        <div class="panel-eyebrow">
                            COURSE CONTENT
                        </div>

                        <h2>
                            Description
                        </h2>

                        <p>
                            Maintain the catalogue summary and detailed learner-facing course context.
                        </p>

                    </div>

                </div>


                <div class="education-form-grid">

                    <div class="education-field full">

                        <label for="course-short-description">
                            Short Description
                            <small>Maximum 500 characters</small>
                        </label>

                        <textarea
                            id="course-short-description"
                            name="short_description"
                            maxlength="500"
                            rows="3"
                        >{{ old(
                            'short_description',
                            $course->short_description
                        ) }}</textarea>

                        @error('short_description')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="education-field full">

                        <label for="course-description">
                            Full Description
                            <small>Optional</small>
                        </label>

                        <textarea
                            id="course-description"
                            name="description"
                            rows="9"
                        >{{ old(
                            'description',
                            $course->description
                        ) }}</textarea>

                        @error('description')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 ACCESS + DISPLAY
            ============================================================ --}}

            <div class="education-form-section">

                <div class="education-form-section-head">

                    <div>

                        <div class="panel-eyebrow">
                            ACCESS & DISPLAY
                        </div>

                        <h2>
                            Catalogue Settings
                        </h2>

                        <p>
                            Update entitlement level, order, stable thumbnail reference and featured state.
                        </p>

                    </div>

                </div>


                <div class="education-form-grid">

                    <div class="education-field">

                        <label for="course-access">
                            Access Level
                            <small>Required</small>
                        </label>

                        <select
                            id="course-access"
                            name="access_level"
                            required
                        >

                            @foreach($accessLevels as $accessLevel)

                                <option
                                    value="{{ $accessLevel }}"
                                    @selected(
                                        old(
                                            'access_level',
                                            $course->access_level
                                        ) === $accessLevel
                                    )
                                >
                                    {{ ucfirst(
                                        strtolower($accessLevel)
                                    ) }}
                                </option>

                            @endforeach

                        </select>

                        <span class="field-help">
                            Access controls entitlement classification only. It does not publish or unpublish the course.
                        </span>

                        @error('access_level')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="education-field">

                        <label for="course-sort-order">
                            Display Order
                            <small>Optional</small>
                        </label>

                        <input
                            id="course-sort-order"
                            type="number"
                            name="sort_order"
                            value="{{ old(
                                'sort_order',
                                $course->sort_order
                            ) }}"
                            min="0"
                            max="4294967295"
                            step="1"
                            inputmode="numeric"
                        >

                        @error('sort_order')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="education-field full">

                        <label for="course-thumbnail-path">
                            Thumbnail Cloud Path / Asset Reference
                            <small>Optional</small>
                        </label>

                        <input
                            id="course-thumbnail-path"
                            type="text"
                            name="thumbnail_path"
                            value="{{ old(
                                'thumbnail_path',
                                $course->thumbnail_path
                            ) }}"
                            maxlength="500"
                            spellcheck="false"
                            placeholder="education/courses/smc-pro/thumbnail.webp"
                        >

                        <span class="field-help">
                            Store only the stable cloud path or provider asset reference — never an expiring signed delivery URL.
                        </span>

                        @error('thumbnail_path')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    <div class="education-field full">

                        <label>
                            Catalogue Highlight
                            <small>Optional</small>
                        </label>

                        <input
                            type="hidden"
                            name="is_featured"
                            value="0"
                        >

                        <div class="education-checkbox">

                            <input
                                id="course-featured"
                                type="checkbox"
                                name="is_featured"
                                value="1"
                                @checked(
                                    (bool) old(
                                        'is_featured',
                                        $course->is_featured
                                    )
                                )
                            >

                            <label for="course-featured">

                                <span>

                                    <strong>
                                        Feature this course
                                    </strong>

                                    Toggle catalogue highlighting. This setting does not affect publication state.

                                </span>

                            </label>

                        </div>

                        @error('is_featured')
                            <span class="field-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>

                </div>

            </div>


            {{-- ============================================================
                 ACTIONS
            ============================================================ --}}

            <div class="education-form-actions">

                <a
                    href="{{ route(
                        'admin.education.courses.show',
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
                    Save Course Changes
                </button>

            </div>

        </section>


        {{-- ============================================================
             COURSE STATE SIDEBAR
        ============================================================ --}}

        <aside class="panel education-side-card">

            <div class="education-state-badge">
                ● {{ $course->status }}
            </div>

            <h3>
                Course state is protected
            </h3>

            <p>
                Editing metadata cannot directly change publication status or publication timestamps.
            </p>


            <div class="education-side-list">

                <div class="education-side-item">

                    <span>
                        Course ID
                    </span>

                    <strong>
                        #{{ $course->id }}
                    </strong>

                </div>


                <div class="education-side-item">

                    <span>
                        UUID
                    </span>

                    <code>
                        {{ $course->uuid }}
                    </code>

                </div>


                <div class="education-side-item">

                    <span>
                        Current Status
                    </span>

                    <strong>
                        {{ $course->status }}
                    </strong>

                </div>


                <div class="education-side-item">

                    <span>
                        Published At
                    </span>

                    <strong>
                        {{ $course->published_at
                            ? $course->published_at->format('d M Y H:i:s')
                            : 'Not published'
                        }}
                    </strong>

                </div>


                <div class="education-side-item">

                    <span>
                        Last Updated
                    </span>

                    <strong>
                        {{ $course->updated_at->format(
                            'd M Y H:i:s'
                        ) }}
                    </strong>

                </div>

            </div>


            <div class="education-warning">
                Publish / Unpublish remains a separate action protected by the dedicated education.publish permission.
            </div>

        </aside>

    </div>

</form>

@endsection


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const codeInput = document.getElementById('course-code');
        const languageInput = document.getElementById('course-language');

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

        if (languageInput) {
            languageInput.addEventListener('blur', function () {
                languageInput.value = languageInput.value
                    .trim()
                    .replace('_', '-');
            });
        }
    });
</script>
@endpush