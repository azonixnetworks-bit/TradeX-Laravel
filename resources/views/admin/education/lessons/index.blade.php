{{-- TradeX Admin — ADMIN-03 Lesson Management --}}

@extends('admin.layouts.app')

@section('title', $module->title . ' Lessons | TradeX Education')

@section('page-title', 'Education')


@push('styles')
<style>
    .lesson-context-banner {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 22px;
        padding: 22px 24px;
        margin-bottom: 24px;
        overflow: hidden;
        position: relative;
    }

    .lesson-context-banner::after {
        content: "";
        position: absolute;
        width: 210px;
        height: 210px;
        right: -76px;
        top: -92px;
        border-radius: 50%;
        background: radial-gradient(
            circle,
            rgba(218, 178, 88, 0.13),
            transparent 70%
        );
        pointer-events: none;
    }

    .lesson-context-copy {
        min-width: 0;
        position: relative;
        z-index: 1;
    }

    .lesson-context-copy h2 {
        margin: 6px 0 7px;
        font-size: 20px;
    }

    .lesson-context-copy p {
        margin: 0;
        max-width: 760px;
        opacity: 0.62;
        line-height: 1.6;
        font-size: 13px;
    }

    .lesson-context-path {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 13px;
    }

    .lesson-context-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.025);
        font-size: 10px;
        line-height: 1;
        opacity: 0.8;
    }

    .lesson-context-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .lesson-section-body {
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .lesson-list {
        display: grid;
        gap: 14px;
    }

    .lesson-card {
        display: grid;
        grid-template-columns: 64px minmax(0, 1fr) 154px 196px;
        gap: 16px;
        align-items: center;
        padding: 17px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.022);
    }

    .lesson-order {
        display: grid;
        place-items: center;
        width: 56px;
        height: 56px;
        border-radius: 13px;
        background: rgba(218, 178, 88, 0.09);
        color: #ddb65e;
        font-weight: 800;
        font-size: 13px;
    }

    .lesson-copy {
        min-width: 0;
    }

    .lesson-copy strong {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .lesson-copy code {
        display: inline-block;
        margin-bottom: 7px;
        font-size: 11px;
        opacity: 0.68;
    }

    .lesson-copy p {
        margin: 0;
        font-size: 12px;
        line-height: 1.55;
        opacity: 0.56;
    }

    .lesson-heading {
        display: block;
        margin: 0 0 6px;
        font-size: 11px;
        font-weight: 700;
        color: #ddb65e;
        opacity: 0.9;
    }

    .lesson-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .lesson-preview-badge,
    .lesson-standard-badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 5px 8px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .lesson-preview-badge {
        border: 1px solid rgba(93, 190, 255, 0.24);
        background: rgba(93, 190, 255, 0.08);
        color: #83cfff;
    }

    .lesson-standard-badge {
        border: 1px solid rgba(255, 255, 255, 0.07);
        background: rgba(255, 255, 255, 0.025);
        opacity: 0.6;
    }

    .lesson-metrics {
        display: grid;
        gap: 8px;
    }

    .lesson-metric {
        padding: 9px 10px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.018);
    }

    .lesson-metric span {
        display: block;
        margin-bottom: 3px;
        font-size: 9px;
        opacity: 0.48;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .lesson-metric strong {
        display: block;
        font-size: 12px;
    }

    .lesson-actions {
        display: grid;
        gap: 8px;
    }

    .lesson-actions form {
        margin: 0;
    }

    .lesson-action-link,
    .lesson-action-button {
        display: block;
        box-sizing: border-box;
        width: 100%;
        border-radius: 9px;
        padding: 9px 11px;
        text-align: center;
        font: inherit;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .lesson-action-link {
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: rgba(255, 255, 255, 0.035);
        color: inherit;
    }

    .lesson-action-button.publish {
        border: 1px solid rgba(218, 178, 88, 0.25);
        background: rgba(218, 178, 88, 0.09);
        color: #e0ba65;
    }

    .lesson-action-button.unpublish {
        border: 1px solid rgba(255, 179, 71, 0.21);
        background: rgba(255, 179, 71, 0.07);
        color: #ffc173;
    }

    .lesson-action-button.delete {
        border: 1px solid rgba(255, 91, 91, 0.24);
        background: rgba(255, 91, 91, 0.06);
        color: #ff8585;
    }

    .lesson-action-disabled {
        padding: 9px 10px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 9px;
        font-size: 10px;
        line-height: 1.45;
        opacity: 0.48;
        text-align: center;
    }

    .lesson-publication-reason {
        width: 100%;
        box-sizing: border-box;
        min-height: 58px;
        margin-bottom: 7px;
        padding: 8px 10px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 9px;
        background: rgba(255, 255, 255, 0.025);
        color: inherit;
        resize: vertical;
        outline: none;
        font: inherit;
        font-size: 10px;
        line-height: 1.45;
    }

    .lesson-publication-reason:focus {
        border-color: rgba(218, 178, 88, 0.62);
        box-shadow: 0 0 0 3px rgba(218, 178, 88, 0.07);
    }

    .lesson-empty {
        padding: 46px 20px;
        text-align: center;
    }

    .lesson-empty .empty-icon {
        margin-bottom: 12px;
        font-size: 30px;
        color: #ddb65e;
    }

    .lesson-empty strong {
        display: block;
        margin-bottom: 7px;
    }

    .lesson-empty span {
        display: block;
        max-width: 560px;
        margin: 0 auto;
        opacity: 0.58;
        line-height: 1.65;
    }

    .lesson-empty .primary-btn {
        display: inline-block;
        margin-top: 18px;
    }

    @media (max-width: 1120px) {
        .lesson-card {
            grid-template-columns: 58px minmax(0, 1fr) 140px;
        }

        .lesson-actions {
            grid-column: 2 / -1;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .lesson-context-banner {
            flex-direction: column;
        }

        .lesson-context-meta {
            justify-content: flex-start;
        }

        .lesson-card {
            grid-template-columns: 52px minmax(0, 1fr);
        }

        .lesson-metrics,
        .lesson-actions {
            grid-column: 2;
        }

        .lesson-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .lesson-actions {
            grid-template-columns: 1fr;
        }

        .lesson-order {
            width: 48px;
            height: 48px;
        }
    }

    @media (max-width: 520px) {
        .lesson-section-body {
            padding: 16px;
        }

        .lesson-card {
            padding: 14px;
        }

        .lesson-metrics {
            grid-template-columns: 1fr;
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
            Module
            <span>
                Lessons
            </span>
        </h1>

        <p>
            Build, order and publish the lesson structure inside this TradeX course module.
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


{{-- ============================================================
     PARENT COURSE + MODULE CONTEXT
============================================================ --}}

<section class="panel lesson-context-banner">

    <div class="lesson-context-copy">

        <div class="panel-eyebrow">
            {{ $module->code }}
        </div>

        <h2>
            {{ $module->title }}
        </h2>

        <p>
            {{ $module->short_description
                ?: 'No short description has been added for this module.'
            }}
        </p>


        <div class="lesson-context-path">

            <span class="lesson-context-chip">
                Course:
                <strong>
                    {{ $course->title }}
                </strong>
            </span>

            <span class="lesson-context-chip">
                Module Order:
                <strong>
                    {{ number_format($module->sort_order) }}
                </strong>
            </span>

        </div>

    </div>


    <div class="lesson-context-meta">

        @php
            $moduleStatusClass = match ($module->status) {
                'PUBLISHED' => 'status-active',
                'ARCHIVED' => 'status-blocked',
                default => 'status-pending',
            };
        @endphp

        <span class="user-status {{ $moduleStatusClass }}">

            <span class="user-status-dot"></span>

            {{ $module->status }}

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

    </div>

</section>


{{-- ============================================================
     LESSON SUMMARY
============================================================ --}}

<div class="user-stat-grid">

    <div class="user-stat-card">

        <span>
            Total Lessons
        </span>

        <strong>
            {{ number_format($stats['total']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Published
        </span>

        <strong class="text-green">
            {{ number_format($stats['published']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Draft
        </span>

        <strong class="text-orange">
            {{ number_format($stats['draft']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Archived
        </span>

        <strong class="text-red">
            {{ number_format($stats['archived']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Preview Lessons
        </span>

        <strong class="text-gold">
            {{ number_format($stats['preview']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Videos
        </span>

        <strong>
            {{ number_format($stats['videos']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Assignments
        </span>

        <strong>
            {{ number_format($stats['assignments']) }}
        </strong>

    </div>

</div>


{{-- ============================================================
     LESSON LIST
============================================================ --}}

<section class="panel users-panel">

    <div class="panel-header">

        <div>

            <div class="panel-eyebrow">
                MODULE CURRICULUM
            </div>

            <h2>
                Lessons
            </h2>

        </div>


        <div>

            @if($permissions['create'] ?? false)

                <a
                    href="{{ route(
                        'admin.education.courses.modules.lessons.create',
                        [
                            'course' => $course,
                            'module' => $module,
                        ]
                    ) }}"
                    class="primary-btn"
                >
                    + New Lesson
                </a>

            @endif

        </div>

    </div>


    <div class="lesson-section-body">

        @forelse($lessons as $lesson)

            @if($loop->first)
                <div class="lesson-list">
            @endif

                @php
                    $lessonStatusClass = match ($lesson->status) {
                        'PUBLISHED' => 'status-active',
                        'ARCHIVED' => 'status-blocked',
                        default => 'status-pending',
                    };

                    $videoCount = (int) $lesson->videos_count;
                    $assignmentCount = (int) $lesson->assignments_count;

                    $canDeleteLesson =
                        ($permissions['delete'] ?? false)
                        && $lesson->status !== 'PUBLISHED'
                        && $videoCount === 0
                        && $assignmentCount === 0;
                @endphp

                <article class="lesson-card">

                    <div class="lesson-order">
                        {{ str_pad(
                            (string) $loop->iteration,
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) }}
                    </div>


                    <div class="lesson-copy">

                        <strong>
                            {{ $lesson->title }}
                        </strong>

                        <code>
                            {{ $lesson->code }}
                            ·
                            {{ $lesson->slug }}
                        </code>


                        @if($lesson->heading)

                            <span class="lesson-heading">
                                {{ $lesson->heading }}
                            </span>

                        @endif


                        <p>
                            {{ $lesson->short_description
                                ?: 'No lesson summary has been added yet.'
                            }}
                        </p>


                        <div class="lesson-badges">

                            @if($lesson->is_preview)

                                <span class="lesson-preview-badge">
                                    Preview Enabled
                                </span>

                            @else

                                <span class="lesson-standard-badge">
                                    Standard Lesson
                                </span>

                            @endif

                        </div>

                    </div>


                    <div class="lesson-metrics">

                        <div class="lesson-metric">

                            <span>
                                Status
                            </span>

                            <div class="user-status {{ $lessonStatusClass }}">

                                <span class="user-status-dot"></span>

                                {{ $lesson->status }}

                            </div>

                        </div>


                        <div class="lesson-metric">

                            <span>
                                Videos
                            </span>

                            <strong>
                                {{ number_format($videoCount) }}
                            </strong>

                        </div>


                        <div class="lesson-metric">

                            <span>
                                Assignments
                            </span>

                            <strong>
                                {{ number_format($assignmentCount) }}
                            </strong>

                        </div>


                        <div class="lesson-metric">

                            <span>
                                Display Order
                            </span>

                            <strong>
                                {{ number_format($lesson->sort_order) }}
                            </strong>

                        </div>


                        <div class="lesson-metric">

                            <span>
                                Published
                            </span>

                            <strong>
                                {{ $lesson->published_at
                                    ? $lesson->published_at->format('d M Y')
                                    : '—'
                                }}
                            </strong>

                        </div>

                    </div>


                    <div class="lesson-actions">

                        @if($permissions['view'] ?? false)

                            <a
                                href="{{ route(
                                    'admin.education.courses.modules.lessons.videos.index',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                        'lesson' => $lesson,
                                    ]
                                ) }}"
                                class="lesson-action-link"
                            >
                                Manage Videos
                            </a>

                            <a
                                href="{{ route(
                                    'admin.education.courses.modules.lessons.assignments.index',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                        'lesson' => $lesson,
                                    ]
                                ) }}"
                                class="lesson-action-link"
                            >
                                Manage Assignments
                            </a>

                        @endif


                        @if($permissions['update'] ?? false)

                            <a
                                href="{{ route(
                                    'admin.education.courses.modules.lessons.edit',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                        'lesson' => $lesson,
                                    ]
                                ) }}"
                                class="lesson-action-link"
                            >
                                Edit Lesson
                            </a>

                        @endif


                        @if(
                            ($permissions['publish'] ?? false)
                            && $lesson->status === 'DRAFT'
                        )

                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.education.courses.modules.lessons.publication',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                        'lesson' => $lesson,
                                    ]
                                ) }}"
                               
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
                                    class="lesson-publication-reason"
                                    maxlength="1000"
                                    placeholder="Optional audit reason"
                                ></textarea>

                                <button
                                    type="submit"
                                    class="lesson-action-button publish"
                                >
                                    Publish Lesson
                                </button>
                            </form>

                        @elseif(
                            ($permissions['publish'] ?? false)
                            && $lesson->status === 'PUBLISHED'
                        )

                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.education.courses.modules.lessons.publication',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                        'lesson' => $lesson,
                                    ]
                                ) }}"
                                onsubmit="return confirm('Unpublish this lesson and return it to Draft?');"
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
                                    class="lesson-publication-reason"
                                    maxlength="1000"
                                    placeholder="Optional audit reason"
                                ></textarea>

                                <button
                                    type="submit"
                                    class="lesson-action-button unpublish"
                                >
                                    Unpublish Lesson
                                </button>
                            </form>

                        @elseif(
                            ($permissions['publish'] ?? false)
                            && $lesson->status === 'ARCHIVED'
                        )

                            <div class="lesson-action-disabled">
                                Archived lesson publication is unavailable.
                            </div>

                        @endif


                        @if($canDeleteLesson)

                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.education.courses.modules.lessons.destroy',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                        'lesson' => $lesson,
                                    ]
                                ) }}"
                                onsubmit="return confirm('Delete this lesson? This performs a soft delete.');"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="lesson-action-button delete"
                                >
                                    Delete Lesson
                                </button>
                            </form>

                        @elseif($permissions['delete'] ?? false)

                            @if($lesson->status === 'PUBLISHED')

                                <div class="lesson-action-disabled">
                                    Unpublish before deletion.
                                </div>

                            @elseif(
                                $videoCount > 0
                                || $assignmentCount > 0
                            )

                                <div class="lesson-action-disabled">
                                    Remove videos and assignments before deletion.
                                </div>

                            @endif

                        @endif


                        @if(
                            ! ($permissions['update'] ?? false)
                            && ! ($permissions['publish'] ?? false)
                            && ! ($permissions['delete'] ?? false)
                        )

                            <div class="lesson-action-disabled">
                                View-only access.
                            </div>

                        @endif

                    </div>

                </article>

            @if($loop->last)
                </div>
            @endif

        @empty

            <div class="lesson-empty">

                <div class="empty-icon">
                    ▤
                </div>

                <strong>
                    No lessons added yet
                </strong>

                <span>
                    Create the first lesson to begin building this module's ordered learning experience.
                </span>


                @if($permissions['create'] ?? false)

                    <a
                        href="{{ route(
                            'admin.education.courses.modules.lessons.create',
                            [
                                'course' => $course,
                                'module' => $module,
                            ]
                        ) }}"
                        class="primary-btn"
                    >
                        + Create First Lesson
                    </a>

                @endif

            </div>

        @endforelse

    </div>

</section>

@endsection
