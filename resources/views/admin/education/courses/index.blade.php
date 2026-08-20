{{-- TradeX Admin — ADMIN-03 Education Course Management --}}

@extends('admin.layouts.app')

@section('title', 'TradeX Education Courses')

@section('page-title', 'Education')


@section('content')

<div class="page-header">

    <div>

        <div class="page-eyebrow">
            EDUCATION MANAGEMENT
        </div>

        <h1>
            TradeX
            <span>
                Courses
            </span>
        </h1>

        <p>
            Manage course structure, access level, publication state and learning content.
        </p>

    </div>


    @if($permissions['create'] ?? false)

        <div>

            <a
                href="{{ route('admin.education.courses.create') }}"
                class="primary-btn"
            >
                + New Course
            </a>

        </div>

    @endif

</div>


{{-- ============================================================
     FLASH MESSAGE
============================================================ --}}

@if(session('success'))

    <section class="panel">

        <strong class="text-green">
            {{ session('success') }}
        </strong>

    </section>

@endif


{{-- ============================================================
     COURSE SUMMARY
============================================================ --}}

<div class="user-stat-grid">

    <div class="user-stat-card">

        <span>
            Total Courses
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
            Free
        </span>

        <strong>
            {{ number_format($stats['free']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Premium
        </span>

        <strong class="text-gold">
            {{ number_format($stats['premium']) }}
        </strong>

    </div>

</div>


{{-- ============================================================
     SEARCH + FILTERS
============================================================ --}}

<section class="panel user-filter-panel">

    <form
        method="GET"
        action="{{ route('admin.education.courses.index') }}"
        class="user-filter-form"
    >

        <div class="filter-search">

            <label for="course-search">
                Search
            </label>

            <input
                id="course-search"
                type="text"
                name="search"
                value="{{ request('search') }}"
                maxlength="100"
                placeholder="Title, code, slug or UUID..."
            >

        </div>


        <div class="filter-item">

            <label for="course-status">
                Status
            </label>

            <select
                id="course-status"
                name="status"
            >

                <option value="">
                    All
                </option>

                @foreach([
                    'DRAFT',
                    'PUBLISHED',
                    'ARCHIVED',
                ] as $status)

                    <option
                        value="{{ $status }}"
                        @selected(
                            request('status')
                            === $status
                        )
                    >
                        {{ ucfirst(
                            strtolower($status)
                        ) }}
                    </option>

                @endforeach

            </select>

        </div>


        <div class="filter-item">

            <label for="course-access">
                Access
            </label>

            <select
                id="course-access"
                name="access_level"
            >

                <option value="">
                    All
                </option>

                <option
                    value="FREE"
                    @selected(
                        request('access_level')
                        === 'FREE'
                    )
                >
                    Free
                </option>

                <option
                    value="PREMIUM"
                    @selected(
                        request('access_level')
                        === 'PREMIUM'
                    )
                >
                    Premium
                </option>

            </select>

        </div>


        <div class="filter-item">

            <label for="course-language">
                Language
            </label>

            <input
                id="course-language"
                type="text"
                name="language_code"
                value="{{ request('language_code') }}"
                maxlength="10"
                placeholder="ta / en / si"
            >

        </div>


        <div class="filter-item">

            <label for="course-sort">
                Sort
            </label>

            <select
                id="course-sort"
                name="sort"
            >

                <option
                    value="display_order"
                    @selected(
                        request(
                            'sort',
                            'display_order'
                        ) === 'display_order'
                    )
                >
                    Display Order
                </option>

                <option
                    value="newest"
                    @selected(
                        request('sort')
                        === 'newest'
                    )
                >
                    Newest
                </option>

                <option
                    value="oldest"
                    @selected(
                        request('sort')
                        === 'oldest'
                    )
                >
                    Oldest
                </option>

                <option
                    value="title_asc"
                    @selected(
                        request('sort')
                        === 'title_asc'
                    )
                >
                    Title A-Z
                </option>

                <option
                    value="title_desc"
                    @selected(
                        request('sort')
                        === 'title_desc'
                    )
                >
                    Title Z-A
                </option>

            </select>

        </div>


        <div class="filter-item">

            <label for="course-per-page">
                Per Page
            </label>

            <select
                id="course-per-page"
                name="per_page"
            >

                @foreach([
                    10,
                    20,
                    50,
                    100,
                ] as $size)

                    <option
                        value="{{ $size }}"
                        @selected(
                            (int) request(
                                'per_page',
                                20
                            ) === $size
                        )
                    >
                        {{ $size }}
                    </option>

                @endforeach

            </select>

        </div>


        <div class="filter-actions">

            <button
                type="submit"
                class="primary-btn"
            >
                Apply
            </button>


            <a
                href="{{ route('admin.education.courses.index') }}"
                class="secondary-btn"
            >
                Reset
            </a>

        </div>

    </form>

</section>


{{-- ============================================================
     COURSE TABLE
============================================================ --}}

<section class="panel users-panel">

    <div class="panel-header">

        <div>

            <div class="panel-eyebrow">
                LEARNING CATALOGUE
            </div>

            <h2>
                Courses
            </h2>

        </div>


        <div class="panel-count">

            @if($courses->total() > 0)

                Showing
                {{ number_format($courses->firstItem()) }}
                –
                {{ number_format($courses->lastItem()) }}

                of

                {{ number_format($courses->total()) }}

            @else

                No courses

            @endif

        </div>

    </div>


    <div class="users-table-wrap">

        <table class="users-table">

            <thead>

                <tr>
                    <th>Course</th>
                    <th>Language</th>
                    <th>Access</th>
                    <th>Status</th>
                    <th>Modules</th>
                    <th>Order</th>
                    <th>Updated</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

            @forelse($courses as $course)

                @php
                    $courseStatusClass = match ($course->status) {
                        'PUBLISHED' => 'status-active',
                        'ARCHIVED' => 'status-blocked',
                        default => 'status-pending',
                    };
                @endphp

                <tr>

                    {{-- COURSE --}}
                    <td>

                        <div class="table-user">

                            <div class="table-avatar">
                                {{ strtoupper(
                                    substr(
                                        $course->title,
                                        0,
                                        1
                                    )
                                ) }}
                            </div>


                            <div>

                                <strong>
                                    {{ $course->title }}
                                </strong>

                                <span>
                                    {{ $course->code }}
                                    ·
                                    ID #{{ $course->id }}
                                </span>

                                @if($course->short_description)

                                    <span>
                                        {{ \Illuminate\Support\Str::limit(
                                            $course->short_description,
                                            72
                                        ) }}
                                    </span>

                                @endif

                            </div>

                        </div>

                    </td>


                    {{-- LANGUAGE --}}
                    <td>

                        <code class="referral-code">
                            {{ strtoupper(
                                $course->language_code
                            ) }}
                        </code>

                    </td>


                    {{-- ACCESS --}}
                    <td>

                        <span
                            class="account-badge
                            {{ $course->access_level === 'PREMIUM'
                                ? 'premium'
                                : 'free'
                            }}"
                        >
                            {{ $course->access_level }}
                        </span>

                    </td>


                    {{-- STATUS --}}
                    <td>

                        <span
                            class="user-status {{ $courseStatusClass }}"
                        >

                            <span class="user-status-dot"></span>

                            {{ $course->status }}

                        </span>

                    </td>


                    {{-- MODULE COUNT --}}
                    <td>

                        <strong>
                            {{ number_format(
                                $course->modules_count
                            ) }}
                        </strong>

                        <span>
                            {{ $course->modules_count === 1
                                ? ' module'
                                : ' modules'
                            }}
                        </span>

                    </td>


                    {{-- DISPLAY ORDER --}}
                    <td>

                        <code class="referral-code">
                            {{ number_format(
                                $course->sort_order
                            ) }}
                        </code>

                        @if($course->is_featured)

                            <div>
                                <span class="text-gold">
                                    Featured
                                </span>
                            </div>

                        @endif

                    </td>


                    {{-- UPDATED --}}
                    <td>

                        <div class="table-date">

                            <strong>
                                {{ $course->updated_at
                                    ->format('d M Y')
                                }}
                            </strong>

                            <span>
                                {{ $course->updated_at
                                    ->format('H:i')
                                }}
                            </span>

                        </div>

                    </td>


                    {{-- ACTION --}}
                    <td>

                        <a
                            href="{{ route(
                                'admin.education.courses.show',
                                $course
                            ) }}"
                            class="table-action"
                            title="View Course"
                            aria-label="View {{ $course->title }}"
                        >
                            →
                        </a>

                    </td>

                </tr>

            @empty

                <tr>

                    <td
                        colspan="8"
                        class="users-empty"
                    >

                        <div class="empty-icon">
                            ◈
                        </div>

                        <strong>
                            No courses found
                        </strong>

                        <span>
                            @if(
                                request()->filled('search')
                                || request()->filled('status')
                                || request()->filled('access_level')
                                || request()->filled('language_code')
                            )
                                Try changing the current search or filters.
                            @elseif($permissions['create'] ?? false)
                                Create the first TradeX education course to begin building the learning catalogue.
                            @else
                                No education courses are currently available.
                            @endif
                        </span>


                        @if(
                            ($permissions['create'] ?? false)
                            && ! request()->filled('search')
                            && ! request()->filled('status')
                            && ! request()->filled('access_level')
                            && ! request()->filled('language_code')
                        )

                            <div>

                                <a
                                    href="{{ route(
                                        'admin.education.courses.create'
                                    ) }}"
                                    class="primary-btn"
                                >
                                    + Create Course
                                </a>

                            </div>

                        @endif

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>


    @include(
        'admin.components.pagination',
        [
            'paginator' => $courses
        ]
    )

</section>

@endsection