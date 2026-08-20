{{-- TradeX Admin — ADMIN-03 Course Details --}}

@extends('admin.layouts.app')

@section('title', $course->title . ' | TradeX Education')

@section('page-title', 'Education')


@push('styles')
<style>
    .course-detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 24px;
        align-items: start;
    }

    .course-hero {
        padding: 26px;
        overflow: hidden;
        position: relative;
    }

    .course-hero::after {
        content: "";
        position: absolute;
        width: 220px;
        height: 220px;
        right: -80px;
        top: -90px;
        border-radius: 50%;
        background: radial-gradient(
            circle,
            rgba(218, 178, 88, 0.13),
            transparent 70%
        );
        pointer-events: none;
    }

    .course-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        position: relative;
        z-index: 1;
    }

    .course-hero-title {
        min-width: 0;
    }

    .course-hero-title h2 {
        margin: 7px 0 8px;
        font-size: clamp(24px, 3vw, 34px);
        line-height: 1.2;
    }

    .course-hero-title p {
        margin: 0;
        max-width: 760px;
        opacity: 0.68;
        line-height: 1.65;
    }

    .course-status-stack {
        display: flex;
        align-items: flex-end;
        flex-direction: column;
        gap: 8px;
        flex-shrink: 0;
    }

    .course-meta-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 24px;
        position: relative;
        z-index: 1;
    }

    .course-meta-card {
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 13px;
        background: rgba(255, 255, 255, 0.025);
    }

    .course-meta-card span {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        opacity: 0.55;
        text-transform: uppercase;
        letter-spacing: 0.07em;
    }

    .course-meta-card strong {
        display: block;
        overflow-wrap: anywhere;
        font-size: 13px;
    }

    .course-section {
        margin-top: 24px;
        overflow: hidden;
    }

    .course-section-body {
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .course-description {
        white-space: pre-line;
        line-height: 1.8;
        opacity: 0.82;
    }

    .course-info-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 13px;
        background: rgba(255, 255, 255, 0.06);
    }

    .course-info-item {
        min-width: 0;
        padding: 15px;
        background: rgba(10, 12, 16, 0.96);
    }

    .course-info-item span {
        display: block;
        margin-bottom: 5px;
        font-size: 11px;
        opacity: 0.52;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .course-info-item strong,
    .course-info-item code {
        display: block;
        max-width: 100%;
        overflow-wrap: anywhere;
        line-height: 1.55;
    }

    .course-module-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 10px;
    }

    .course-modules-list {
        display: grid;
        gap: 12px;
    }

    .course-module-card {
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 13px;
        background: rgba(255, 255, 255, 0.023);
    }

    .course-module-order {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background: rgba(218, 178, 88, 0.09);
        color: #ddb65e;
        font-size: 13px;
        font-weight: 800;
    }

    .course-module-copy {
        min-width: 0;
    }

    .course-module-copy strong {
        display: block;
        margin-bottom: 5px;
    }

    .course-module-copy span {
        display: block;
        font-size: 12px;
        opacity: 0.58;
        line-height: 1.55;
    }

    .course-module-side {
        text-align: right;
    }

    .course-module-side strong {
        display: block;
        margin-bottom: 4px;
    }

    .course-module-side span {
        font-size: 11px;
        opacity: 0.55;
    }

    .course-empty {
        padding: 34px 20px;
        text-align: center;
    }

    .course-empty b {
        display: block;
        margin-bottom: 7px;
    }

    .course-empty span {
        display: block;
        max-width: 520px;
        margin: 0 auto;
        opacity: 0.58;
        line-height: 1.65;
    }

    .course-side {
        display: grid;
        gap: 18px;
        position: sticky;
        top: 92px;
    }

    .course-side-card {
        padding: 21px;
    }

    .course-side-card h3 {
        margin: 0 0 8px;
        font-size: 15px;
    }

    .course-side-card > p {
        margin: 0;
        font-size: 12px;
        opacity: 0.62;
        line-height: 1.65;
    }

    .course-action-list {
        display: grid;
        gap: 10px;
        margin-top: 17px;
    }

    .course-action-list .primary-btn,
    .course-action-list .secondary-btn {
        box-sizing: border-box;
        width: 100%;
        text-align: center;
    }

    .course-action-list form {
        margin: 0;
    }

    .course-action-list button {
        width: 100%;
    }

    .course-danger-btn {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid rgba(255, 91, 91, 0.28);
        border-radius: 10px;
        background: rgba(255, 91, 91, 0.07);
        color: #ff8585;
        padding: 11px 14px;
        cursor: pointer;
        font: inherit;
        font-weight: 700;
    }

    .course-danger-btn:hover {
        background: rgba(255, 91, 91, 0.12);
    }

    .course-disabled-action {
        padding: 12px 13px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.025);
        font-size: 11px;
        line-height: 1.55;
        opacity: 0.55;
    }

    .publication-reason {
        width: 100%;
        box-sizing: border-box;
        margin: 10px 0 0;
        border: 1px solid rgba(255, 255, 255, 0.09);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.03);
        color: inherit;
        padding: 10px 12px;
        resize: vertical;
        min-height: 70px;
        outline: none;
        font: inherit;
        font-size: 12px;
    }

    .publication-reason:focus {
        border-color: rgba(218, 178, 88, 0.68);
        box-shadow: 0 0 0 3px rgba(218, 178, 88, 0.08);
    }

    .course-audit-list {
        display: grid;
        gap: 12px;
        margin-top: 17px;
    }

    .course-audit-row {
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .course-audit-row:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .course-audit-row span {
        display: block;
        margin-bottom: 4px;
        font-size: 10px;
        opacity: 0.48;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .course-audit-row strong {
        display: block;
        font-size: 12px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    @media (max-width: 1120px) {
        .course-detail-grid {
            grid-template-columns: 1fr;
        }

        .course-side {
            position: static;
        }
    }

    @media (max-width: 820px) {
        .course-meta-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .course-info-list {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 620px) {
        .course-hero-top {
            flex-direction: column;
        }

        .course-status-stack {
            align-items: flex-start;
        }

        .course-meta-grid {
            grid-template-columns: 1fr;
        }

        .course-module-card {
            grid-template-columns: 44px minmax(0, 1fr);
        }

        .course-module-order {
            width: 44px;
            height: 44px;
        }

        .course-module-side {
            grid-column: 2;
            text-align: left;
        }
    }
</style>
@endpush


@section('content')

@php
    $statusClass = match ($course->status) {
        'PUBLISHED' => 'status-active',
        'ARCHIVED' => 'status-blocked',
        default => 'status-pending',
    };

    $canView = $permissions['view'] ?? false;
    $canUpdate = $permissions['update'] ?? false;
    $canDelete = $permissions['delete'] ?? false;
    $canPublish = $permissions['publish'] ?? false;
@endphp


<div class="page-header">

    <div>

        <div class="page-eyebrow">
            EDUCATION MANAGEMENT
        </div>

        <h1>
            Course
            <span>
                Details
            </span>
        </h1>

        <p>
            Review course configuration, curriculum structure and publication state.
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


<div class="course-detail-grid">

    <div>

        {{-- ============================================================
             COURSE HERO
        ============================================================ --}}

        <section class="panel course-hero">

            <div class="course-hero-top">

                <div class="course-hero-title">

                    <div class="panel-eyebrow">
                        {{ $course->code }}
                    </div>

                    <h2>
                        {{ $course->title }}
                    </h2>

                    <p>
                        {{ $course->short_description
                            ?: 'No short description has been added for this course yet.'
                        }}
                    </p>

                </div>


                <div class="course-status-stack">

                    <span
                        class="user-status {{ $statusClass }}"
                    >
                        <span class="user-status-dot"></span>

                        {{ $course->status }}
                    </span>


                    <span
                        class="account-badge
                        {{ $course->access_level === 'PREMIUM'
                            ? 'premium'
                            : 'free'
                        }}"
                    >
                        {{ $course->access_level }}
                    </span>

                    @if($course->is_featured)

                        <span class="text-gold">
                            ★ Featured
                        </span>

                    @endif

                </div>

            </div>


            <div class="course-meta-grid">

                <div class="course-meta-card">

                    <span>
                        Language
                    </span>

                    <strong>
                        {{ strtoupper(
                            $course->language_code
                        ) }}
                    </strong>

                </div>


                <div class="course-meta-card">

                    <span>
                        Modules
                    </span>

                    <strong>
                        {{ number_format(
                            $course->modules->count()
                        ) }}
                    </strong>

                </div>


                <div class="course-meta-card">

                    <span>
                        Display Order
                    </span>

                    <strong>
                        {{ number_format(
                            $course->sort_order
                        ) }}
                    </strong>

                </div>


                <div class="course-meta-card">

                    <span>
                        Published
                    </span>

                    <strong>
                        {{ $course->published_at
                            ? $course->published_at->format('d M Y H:i')
                            : 'Not published'
                        }}
                    </strong>

                </div>

            </div>

        </section>


        {{-- ============================================================
             DESCRIPTION
        ============================================================ --}}

        <section class="panel course-section">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        COURSE CONTENT
                    </div>

                    <h2>
                        Description
                    </h2>

                </div>

            </div>


            <div class="course-section-body">

                @if($course->description)

                    <div class="course-description">
                        {{ $course->description }}
                    </div>

                @else

                    <div class="course-empty">

                        <b>
                            No detailed description
                        </b>

                        <span>
                            Add learning objectives, intended audience and curriculum context through the Edit Course action.
                        </span>

                    </div>

                @endif

            </div>

        </section>


        {{-- ============================================================
             COURSE METADATA
        ============================================================ --}}

        <section class="panel course-section">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        CONFIGURATION
                    </div>

                    <h2>
                        Course Metadata
                    </h2>

                </div>

            </div>


            <div class="course-section-body">

                <div class="course-info-list">

                    <div class="course-info-item">

                        <span>
                            Course ID
                        </span>

                        <strong>
                            #{{ $course->id }}
                        </strong>

                    </div>


                    <div class="course-info-item">

                        <span>
                            UUID
                        </span>

                        <code>
                            {{ $course->uuid }}
                        </code>

                    </div>


                    <div class="course-info-item">

                        <span>
                            Code
                        </span>

                        <code>
                            {{ $course->code }}
                        </code>

                    </div>


                    <div class="course-info-item">

                        <span>
                            Slug
                        </span>

                        <code>
                            {{ $course->slug }}
                        </code>

                    </div>


                    <div class="course-info-item">

                        <span>
                            Access Level
                        </span>

                        <strong>
                            {{ $course->access_level }}
                        </strong>

                    </div>


                    <div class="course-info-item">

                        <span>
                            Language
                        </span>

                        <strong>
                            {{ $course->language_code }}
                        </strong>

                    </div>


                    <div class="course-info-item">

                        <span>
                            Thumbnail Cloud Reference
                        </span>

                        <code>
                            {{ $course->thumbnail_path
                                ?: 'Not configured'
                            }}
                        </code>

                    </div>


                    <div class="course-info-item">

                        <span>
                            Featured
                        </span>

                        <strong>
                            {{ $course->is_featured
                                ? 'Yes'
                                : 'No'
                            }}
                        </strong>

                    </div>

                </div>

            </div>

        </section>


        {{-- ============================================================
             MODULE OVERVIEW
        ============================================================ --}}

        <section class="panel course-section">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        CURRICULUM
                    </div>

                    <h2>
                        Course Modules
                    </h2>

                </div>


                <div class="course-module-header-actions">

                    <div class="panel-count">
                        {{ number_format(
                            $course->modules->count()
                        ) }}
                        {{ $course->modules->count() === 1
                            ? 'module'
                            : 'modules'
                        }}
                    </div>


                    @if($canView)

                        <a
                            href="{{ route(
                                'admin.education.courses.modules.index',
                                $course
                            ) }}"
                            class="secondary-btn"
                        >
                            Manage Modules
                        </a>

                    @endif

                </div>

            </div>


            <div class="course-section-body">

                @forelse($course->modules as $module)

                    @if($loop->first)
                        <div class="course-modules-list">
                    @endif

                        <article class="course-module-card">

                            <div class="course-module-order">
                                {{ str_pad(
                                    (string) ($loop->iteration),
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                ) }}
                            </div>


                            <div class="course-module-copy">

                                <strong>
                                    {{ $module->title }}
                                </strong>

                                <span>
                                    {{ $module->code }}
                                    ·
                                    {{ $module->status }}

                                    @if($module->short_description)
                                        ·
                                        {{ \Illuminate\Support\Str::limit(
                                            $module->short_description,
                                            80
                                        ) }}
                                    @endif
                                </span>

                            </div>


                            <div class="course-module-side">

                                <strong>
                                    {{ number_format(
                                        $module->lessons_count
                                    ) }}
                                </strong>

                                <span>
                                    {{ $module->lessons_count === 1
                                        ? 'lesson'
                                        : 'lessons'
                                    }}
                                </span>

                            </div>

                        </article>

                    @if($loop->last)
                        </div>
                    @endif

                @empty

                    <div class="course-empty">

                        <b>
                            No modules added yet
                        </b>

                        <span>
                            The course foundation is ready. Use Manage Modules to create and organize the curriculum.
                        </span>

                    </div>

                @endforelse

            </div>

        </section>

    </div>


    {{-- ============================================================
         RIGHT SIDEBAR
    ============================================================ --}}

    <aside class="course-side">

        {{-- ACTIONS --}}
        <section class="panel course-side-card">

            <div class="panel-eyebrow">
                COURSE ACTIONS
            </div>

            <h3>
                Manage Course
            </h3>

            <p>
                Available actions are controlled by the authenticated admin's exact education permissions.
            </p>


            <div class="course-action-list">

                @if($canUpdate)

                    <a
                        href="{{ route(
                            'admin.education.courses.edit',
                            $course
                        ) }}"
                        class="secondary-btn"
                    >
                        Edit Course
                    </a>

                @endif


                @if(
                    $canPublish
                    && $course->status === 'DRAFT'
                )

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.education.courses.publication',
                            $course
                        ) }}"
                        onsubmit="return confirm('Publish this course now?');"
                    >
                        @csrf
                        @method('PATCH')

                        <input
                            type="hidden"
                            name="action"
                            value="PUBLISH"
                        >

                        <textarea
                            name="reason"
                            class="publication-reason"
                            maxlength="1000"
                            placeholder="Optional publication reason / audit note"
                        ></textarea>

                        <button
                            type="submit"
                            class="primary-btn"
                        >
                            Publish Course
                        </button>
                    </form>

                @elseif(
                    $canPublish
                    && $course->status === 'PUBLISHED'
                )

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.education.courses.publication',
                            $course
                        ) }}"
                        onsubmit="return confirm('Unpublish this course and return it to Draft?');"
                    >
                        @csrf
                        @method('PATCH')

                        <input
                            type="hidden"
                            name="action"
                            value="UNPUBLISH"
                        >

                        <textarea
                            name="reason"
                            class="publication-reason"
                            maxlength="1000"
                            placeholder="Optional unpublish reason / audit note"
                        ></textarea>

                        <button
                            type="submit"
                            class="secondary-btn"
                        >
                            Unpublish Course
                        </button>
                    </form>

                @elseif(
                    $canPublish
                    && $course->status === 'ARCHIVED'
                )

                    <div class="course-disabled-action">
                        Archived courses cannot be published through the Draft/Published workflow.
                    </div>

                @endif


                @if(
                    $canDelete
                    && $course->status !== 'PUBLISHED'
                )

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.education.courses.destroy',
                            $course
                        ) }}"
                        onsubmit="return confirm('Delete this course? This action performs a soft delete.');"
                    >
                        @csrf
                        @method('DELETE')

                        <button
                            type="submit"
                            class="course-danger-btn"
                        >
                            Delete Course
                        </button>
                    </form>

                @elseif(
                    $canDelete
                    && $course->status === 'PUBLISHED'
                )

                    <div class="course-disabled-action">
                        Unpublish this course before deleting it.
                    </div>

                @endif


                @if(
                    ! $canUpdate
                    && ! $canPublish
                    && ! $canDelete
                )

                    <div class="course-disabled-action">
                        Your current role has view-only access to this course.
                    </div>

                @endif

            </div>

        </section>


        {{-- AUDIT / OWNERSHIP --}}
        <section class="panel course-side-card">

            <div class="panel-eyebrow">
                AUDIT CONTEXT
            </div>

            <h3>
                Ownership
            </h3>

            <p>
                Administrative attribution is stored with the course and backed by the global audit log.
            </p>


            <div class="course-audit-list">

                <div class="course-audit-row">

                    <span>
                        Created By
                    </span>

                    <strong>
                        {{ $course->createdByAdmin?->name
                            ?? $course->createdByAdmin?->email
                            ?? (
                                $course->created_by_admin_id
                                    ? 'Admin #'.$course->created_by_admin_id
                                    : 'System / Unknown'
                            )
                        }}
                    </strong>

                </div>


                <div class="course-audit-row">

                    <span>
                        Created At
                    </span>

                    <strong>
                        {{ $course->created_at
                            ->format('d M Y H:i:s')
                        }}
                    </strong>

                </div>


                <div class="course-audit-row">

                    <span>
                        Last Updated By
                    </span>

                    <strong>
                        {{ $course->updatedByAdmin?->name
                            ?? $course->updatedByAdmin?->email
                            ?? (
                                $course->updated_by_admin_id
                                    ? 'Admin #'.$course->updated_by_admin_id
                                    : 'System / Unknown'
                            )
                        }}
                    </strong>

                </div>


                <div class="course-audit-row">

                    <span>
                        Last Updated
                    </span>

                    <strong>
                        {{ $course->updated_at
                            ->format('d M Y H:i:s')
                        }}
                    </strong>

                </div>

            </div>

        </section>

    </aside>

</div>

@endsection