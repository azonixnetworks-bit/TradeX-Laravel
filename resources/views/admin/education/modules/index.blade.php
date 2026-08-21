{{-- TradeX Admin ΓÇö ADMIN-03 Module Management --}}

@extends('admin.layouts.app')

@section('title', $course->title . ' Modules | TradeX Education')

@section('page-title', 'Education')


@push('styles')
<style>
    .module-course-banner {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 22px;
        padding: 22px 24px;
        margin-bottom: 24px;
        overflow: hidden;
        position: relative;
    }

    .module-course-banner::after {
        content: "";
        position: absolute;
        width: 190px;
        height: 190px;
        right: -70px;
        top: -85px;
        border-radius: 50%;
        background: radial-gradient(
            circle,
            rgba(218, 178, 88, 0.12),
            transparent 70%
        );
        pointer-events: none;
    }

    .module-course-copy {
        min-width: 0;
        position: relative;
        z-index: 1;
    }

    .module-course-copy h2 {
        margin: 6px 0 7px;
        font-size: 20px;
    }

    .module-course-copy p {
        margin: 0;
        max-width: 760px;
        opacity: 0.62;
        line-height: 1.6;
        font-size: 13px;
    }

    .module-course-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .course-section-body {
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .module-list {
        display: grid;
        gap: 14px;
    }

    .module-card {
        display: grid;
        grid-template-columns: 64px minmax(0, 1fr) 140px 190px;
        gap: 16px;
        align-items: center;
        padding: 17px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.022);
    }

    .module-order {
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

    .module-copy {
        min-width: 0;
    }

    .module-copy strong {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .module-copy code {
        display: inline-block;
        margin-bottom: 6px;
        font-size: 11px;
        opacity: 0.68;
    }

    .module-copy p {
        margin: 0;
        font-size: 12px;
        line-height: 1.55;
        opacity: 0.56;
    }

    .module-metrics {
        display: grid;
        gap: 8px;
    }

    .module-metric {
        padding: 9px 10px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.018);
    }

    .module-metric span {
        display: block;
        margin-bottom: 3px;
        font-size: 9px;
        opacity: 0.48;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .module-metric strong {
        display: block;
        font-size: 12px;
    }

    .module-actions {
        display: grid;
        gap: 8px;
    }

    .module-actions form {
        margin: 0;
    }

    .module-action-link,
    .module-action-button {
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

    .module-action-link {
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: rgba(255, 255, 255, 0.035);
        color: inherit;
    }

    .module-action-button.publish {
        border: 1px solid rgba(218, 178, 88, 0.25);
        background: rgba(218, 178, 88, 0.09);
        color: #e0ba65;
    }

    .module-action-button.unpublish {
        border: 1px solid rgba(255, 179, 71, 0.21);
        background: rgba(255, 179, 71, 0.07);
        color: #ffc173;
    }

    .module-action-button.delete {
        border: 1px solid rgba(255, 91, 91, 0.24);
        background: rgba(255, 91, 91, 0.06);
        color: #ff8585;
    }

    .module-action-disabled {
        padding: 9px 10px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 9px;
        font-size: 10px;
        line-height: 1.45;
        opacity: 0.48;
        text-align: center;
    }

    .module-publication-reason {
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

    .module-publication-reason:focus {
        border-color: rgba(218, 178, 88, 0.62);
        box-shadow: 0 0 0 3px rgba(218, 178, 88, 0.07);
    }

    .module-empty {
        padding: 46px 20px;
        text-align: center;
    }

    .module-empty .empty-icon {
        margin-bottom: 12px;
        font-size: 30px;
        color: #ddb65e;
    }

    .module-empty strong {
        display: block;
        margin-bottom: 7px;
    }

    .module-empty span {
        display: block;
        max-width: 520px;
        margin: 0 auto;
        opacity: 0.58;
        line-height: 1.65;
    }

    .module-empty .primary-btn {
        display: inline-block;
        margin-top: 18px;
    }

    @media (max-width: 1120px) {
        .module-card {
            grid-template-columns: 58px minmax(0, 1fr) 130px;
        }

        .module-actions {
            grid-column: 2 / -1;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .module-course-banner {
            flex-direction: column;
        }

        .module-course-meta {
            justify-content: flex-start;
        }

        .module-card {
            grid-template-columns: 52px minmax(0, 1fr);
        }

        .module-metrics,
        .module-actions {
            grid-column: 2;
        }

        .module-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .module-actions {
            grid-template-columns: 1fr;
        }

        .module-order {
            width: 48px;
            height: 48px;
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
            Course
            <span>
                Modules
            </span>
        </h1>

        <p>
            Build and control the ordered curriculum structure inside this TradeX course.
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
            ΓåÉ Back to Course
        </a>

    </div>

</div>


{{-- ============================================================
     PARENT COURSE CONTEXT
============================================================ --}}

<section class="panel module-course-banner">

    <div class="module-course-copy">

        <div class="panel-eyebrow">
            {{ $course->code }}
        </div>

        <h2>
            {{ $course->title }}
        </h2>

        <p>
            {{ $course->short_description
                ?: 'No short description has been added for this course.'
            }}
        </p>

    </div>


    <div class="module-course-meta">

        <span
            class="account-badge
            {{ $course->access_level === 'PREMIUM'
                ? 'premium'
                : 'free'
            }}"
        >
            {{ $course->access_level }}
        </span>


        @php
            $courseStatusClass = match ($course->status) {
                'PUBLISHED' => 'status-active',
                'ARCHIVED' => 'status-blocked',
                default => 'status-pending',
            };
        @endphp

        <span class="user-status {{ $courseStatusClass }}">

            <span class="user-status-dot"></span>

            {{ $course->status }}

        </span>

    </div>

</section>


{{-- ============================================================
     MODULE SUMMARY
============================================================ --}}

<div class="user-stat-grid">

    <div class="user-stat-card">

        <span>
            Total Modules
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
            Total Lessons
        </span>

        <strong class="text-gold">
            {{ number_format($stats['lessons']) }}
        </strong>

    </div>

</div>


{{-- ============================================================
     MODULE LIST
============================================================ --}}

<section class="panel users-panel">

    <div class="panel-header">

        <div>

            <div class="panel-eyebrow">
                CURRICULUM STRUCTURE
            </div>

            <h2>
                Modules
            </h2>

        </div>


        <div>

            @if($permissions['create'] ?? false)

                <a
                    href="{{ route(
                        'admin.education.courses.modules.create',
                        $course
                    ) }}"
                    class="primary-btn"
                >
                    + New Module
                </a>

            @endif

        </div>

    </div>


    <div class="course-section-body">

        @forelse($modules as $module)

            @if($loop->first)
                <div class="module-list">
            @endif

                @php
                    $moduleStatusClass = match ($module->status) {
                        'PUBLISHED' => 'status-active',
                        'ARCHIVED' => 'status-blocked',
                        default => 'status-pending',
                    };

                    $canDeleteModule =
                        ($permissions['delete'] ?? false)
                        && $module->status !== 'PUBLISHED'
                        && (int) $module->lessons_count === 0;
                @endphp

                <article class="module-card">

                    <div class="module-order">
                        {{ str_pad(
                            (string) ($loop->iteration),
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) }}
                    </div>


                    <div class="module-copy">

                        <strong>
                            {{ $module->title }}
                        </strong>

                        <code>
                            {{ $module->code }}
                            ┬╖
                            {{ $module->slug }}
                        </code>

                        <p>
                            {{ $module->short_description
                                ?: 'No module summary has been added yet.'
                            }}
                        </p>

                    </div>


                    <div class="module-metrics">

                        <div class="module-metric">

                            <span>
                                Status
                            </span>

                            <div class="user-status {{ $moduleStatusClass }}">

                                <span class="user-status-dot"></span>

                                {{ $module->status }}

                            </div>

                        </div>


                        <div class="module-metric">

                            <span>
                                Lessons
                            </span>

                            <strong>
                                {{ number_format(
                                    $module->lessons_count
                                ) }}
                            </strong>

                        </div>


                        <div class="module-metric">

                            <span>
                                Display Order
                            </span>

                            <strong>
                                {{ number_format(
                                    $module->sort_order
                                ) }}
                            </strong>

                        </div>


                        <div class="module-metric">

                            <span>
                                Published
                            </span>

                            <strong>
                                {{ $module->published_at
                                    ? $module->published_at->format('d M Y')
                                    : 'ΓÇö'
                                }}
                            </strong>

                        </div>

                    </div>


                    <div class="module-actions">

                        @if($permissions['view'] ?? false)

                            <a
                                href="{{ route(
                                    'admin.education.courses.modules.lessons.index',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                    ]
                                ) }}"
                                class="module-action-link"
                            >
                                Manage Lessons
                            </a>

                        @endif


                        @if($permissions['update'] ?? false)

                            <a
                                href="{{ route(
                                    'admin.education.courses.modules.edit',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                    ]
                                ) }}"
                                class="module-action-link"
                            >
                                Edit Module
                            </a>

                        @endif


                        @if(
                            ($permissions['publish'] ?? false)
                            && $module->status === 'DRAFT'
                        )

                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.education.courses.modules.publication',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                    ]
                                ) }}"
                                onsubmit="return confirm('Publish this module now?');"
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
                                    class="module-publication-reason"
                                    maxlength="1000"
                                    placeholder="Optional audit reason"
                                ></textarea>

                                <button
                                    type="submit"
                                    class="module-action-button publish"
                                >
                                    Publish Module
                                </button>
                            </form>

                        @elseif(
                            ($permissions['publish'] ?? false)
                            && $module->status === 'PUBLISHED'
                        )

                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.education.courses.modules.publication',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                    ]
                                ) }}"
                                onsubmit="return confirm('Unpublish this module and return it to Draft?');"
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
                                    class="module-publication-reason"
                                    maxlength="1000"
                                    placeholder="Optional audit reason"
                                ></textarea>

                                <button
                                    type="submit"
                                    class="module-action-button unpublish"
                                >
                                    Unpublish Module
                                </button>
                            </form>

                        @elseif(
                            ($permissions['publish'] ?? false)
                            && $module->status === 'ARCHIVED'
                        )

                            <div class="module-action-disabled">
                                Archived module publication is unavailable.
                            </div>

                        @endif


                        @if($canDeleteModule)

                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.education.courses.modules.destroy',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                    ]
                                ) }}"
                                onsubmit="return confirm('Delete this module? This performs a soft delete.');"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="module-action-button delete"
                                >
                                    Delete Module
                                </button>
                            </form>

                        @elseif($permissions['delete'] ?? false)

                            @if($module->status === 'PUBLISHED')

                                <div class="module-action-disabled">
                                    Unpublish before deletion.
                                </div>

                            @elseif((int) $module->lessons_count > 0)

                                <div class="module-action-disabled">
                                    Remove or relocate lessons before deletion.
                                </div>

                            @endif

                        @endif


                        @if(
                            ! ($permissions['update'] ?? false)
                            && ! ($permissions['publish'] ?? false)
                            && ! ($permissions['delete'] ?? false)
                        )

                            <div class="module-action-disabled">
                                View-only access.
                            </div>

                        @endif

                    </div>

                </article>

            @if($loop->last)
                </div>
            @endif

        @empty

            <div class="module-empty">

                <div class="empty-icon">
                    Γûú
                </div>

                <strong>
                    No modules added yet
                </strong>

                <span>
                    Create the first module to begin building the ordered curriculum for this course.
                </span>


                @if($permissions['create'] ?? false)

                    <a
                        href="{{ route(
                            'admin.education.courses.modules.create',
                            $course
                        ) }}"
                        class="primary-btn"
                    >
                        + Create First Module
                    </a>

                @endif

            </div>

        @endforelse

    </div>

</section>

@endsection
