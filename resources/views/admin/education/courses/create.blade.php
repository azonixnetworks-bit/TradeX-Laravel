{{-- TradeX Admin — ADMIN-03 Create Course --}}

@extends('admin.layouts.app')

@section('title', 'Create TradeX Course')

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

    .education-checkbox input {
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

    .education-draft-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
        padding: 7px 10px;
        border: 1px solid rgba(255, 179, 71, 0.24);
        border-radius: 999px;
        background: rgba(255, 179, 71, 0.08);
        color: #ffbd66;
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
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 10px;
        align-items: start;
    }

    .education-side-item b {
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: rgba(218, 178, 88, 0.10);
        color: #ddb65e;
        font-size: 12px;
    }

    .education-side-item strong {
        display: block;
        margin-bottom: 3px;
        font-size: 12px;
    }

    .education-side-item span {
        display: block;
        font-size: 11px;
        line-height: 1.5;
        opacity: 0.56;
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
            Create
            <span>
                Course
            </span>
        </h1>

        <p>
            Create the course foundation first. Modules, lessons, videos and assignments will be managed inside the course afterwards.
        </p>

    </div>


    <div>

        <a
            href="{{ route('admin.education.courses.index') }}"
            class="secondary-btn"
        >
            ← Back to Courses
        </a>

    </div>

</div>


@if($errors->any())

    <div class="education-error-summary">

        <strong>
            Course could not be created.
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
    action="{{ route('admin.education.courses.store') }}"
    autocomplete="off"
>
    @csrf

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
                            Use stable identifiers that can safely be referenced by the admin panel, APIs and future mobile clients.
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
                            value="{{ old('title') }}"
                            maxlength="200"
                            required
                            autofocus
                            placeholder="Example: SMC Professional Trading"
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
                            value="{{ old('code') }}"
                            maxlength="80"
                            required
                            spellcheck="false"
                            placeholder="SMC-PRO-01"
                        >

                        <span class="field-help">
                            Stable internal code. Uppercase letters, numbers, dots, underscores and hyphens only.
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
                            value="{{ old('slug') }}"
                            maxlength="220"
                            required
                            spellcheck="false"
                            placeholder="smc-professional-trading"
                        >

                        <span class="field-help">
                            Lowercase letters, numbers and hyphens. Used as a readable course identifier.
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
                            value="{{ old('language_code', 'ta') }}"
                            maxlength="10"
                            required
                            list="course-language-options"
                            spellcheck="false"
                            placeholder="ta"
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

                        <span class="field-help">
                            ISO-style language code such as ta, en, si, hi or en-US.
                        </span>

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
                            Add concise catalogue text and a detailed description for admins and future learner-facing screens.
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
                            placeholder="A short summary shown in course cards and catalogue views."
                        >{{ old('short_description') }}</textarea>

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
                            placeholder="Explain the course objectives, learning outcome, audience and curriculum context."
                        >{{ old('description') }}</textarea>

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
                            Configure entitlement level, display order and optional presentation metadata.
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
                                            'PREMIUM'
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
                            Premium courses require a valid learner entitlement. Free courses can be exposed without premium course access.
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
                            value="{{ old('sort_order', 0) }}"
                            min="0"
                            max="4294967295"
                            step="1"
                            inputmode="numeric"
                        >

                        <span class="field-help">
                            Lower numbers appear earlier in the default catalogue order.
                        </span>

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
                            value="{{ old('thumbnail_path') }}"
                            maxlength="500"
                            spellcheck="false"
                            placeholder="education/courses/smc-pro/thumbnail.webp"
                        >

                        <span class="field-help">
                            Store a stable cloud object path or asset reference here — not an expiring signed URL.
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

                        <div class="education-checkbox">

                            <input
                                id="course-featured"
                                type="checkbox"
                                name="is_featured"
                                value="1"
                                @checked(
                                    old('is_featured')
                                )
                            >

                            <label for="course-featured">

                                <span>

                                    <strong>
                                        Feature this course
                                    </strong>

                                    Mark the course as a featured catalogue item. This does not publish the course.

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
                    href="{{ route('admin.education.courses.index') }}"
                    class="secondary-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="primary-btn"
                >
                    Create Draft Course
                </button>

            </div>

        </section>


        {{-- ============================================================
             SECURITY / WORKFLOW SIDEBAR
        ============================================================ --}}

        <aside class="panel education-side-card">

            <div class="education-draft-badge">
                ● DRAFT FIRST
            </div>

            <h3>
                Secure publishing workflow
            </h3>

            <p>
                This form only creates the course foundation. A newly created course is always saved as Draft.
            </p>


            <div class="education-side-list">

                <div class="education-side-item">

                    <b>1</b>

                    <div>
                        <strong>
                            Create course
                        </strong>

                        <span>
                            Save title, identifiers, language, access and catalogue settings.
                        </span>
                    </div>

                </div>


                <div class="education-side-item">

                    <b>2</b>

                    <div>
                        <strong>
                            Build curriculum
                        </strong>

                        <span>
                            Add modules, lessons, protected videos and assignment instructions.
                        </span>
                    </div>

                </div>


                <div class="education-side-item">

                    <b>3</b>

                    <div>
                        <strong>
                            Publish separately
                        </strong>

                        <span>
                            Publication requires the dedicated education.publish permission and server-controlled timestamp.
                        </span>
                    </div>

                </div>

            </div>

        </aside>

    </div>

</form>

@endsection


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const titleInput = document.getElementById('course-title');
        const slugInput = document.getElementById('course-slug');
        const codeInput = document.getElementById('course-code');

        if (!titleInput || !slugInput || !codeInput) {
            return;
        }

        let slugEdited = slugInput.value.trim() !== '';

        slugInput.addEventListener('input', function () {
            slugEdited = slugInput.value.trim() !== '';
        });

        titleInput.addEventListener('input', function () {
            if (slugEdited) {
                return;
            }

            slugInput.value = titleInput.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });

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
    });
</script>
@endpush