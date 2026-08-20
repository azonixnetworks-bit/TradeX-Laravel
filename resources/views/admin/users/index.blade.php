{{-- TradeX Admin skeleton --}} 
@extends('admin.layouts.app')

@section('title', 'TradeX Users')

@section('page-title', 'Users')


@section('content')

<div class="page-header">

    <div>

        <div class="page-eyebrow">
            USER MANAGEMENT
        </div>

        <h1>
            TradeX
            <span>
                Users
            </span>
        </h1>

        <p>
            Search, filter and monitor registered TradeX accounts.
        </p>

    </div>

</div>


{{-- ============================================================
     USER SUMMARY
============================================================ --}}

<div class="user-stat-grid">

    <div class="user-stat-card">

        <span>
            Total Users
        </span>

        <strong>
            {{ number_format($stats['total']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Active
        </span>

        <strong class="text-green">
            {{ number_format($stats['active']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Free Users
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


    <div class="user-stat-card">

        <span>
            Suspended
        </span>

        <strong class="text-orange">
            {{ number_format($stats['suspended']) }}
        </strong>

    </div>


    <div class="user-stat-card">

        <span>
            Blocked
        </span>

        <strong class="text-red">
            {{ number_format($stats['blocked']) }}
        </strong>

    </div>

</div>


{{-- ============================================================
     SEARCH + FILTERS
============================================================ --}}

<section class="panel user-filter-panel">

    <form
        method="GET"
        action="{{ route('admin.users.index') }}"
        class="user-filter-form"
    >

        <div class="filter-search">

            <label>
                Search
            </label>

            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Name, email, WhatsApp, referral code..."
            >

        </div>


        <div class="filter-item">

            <label>
                Account
            </label>

            <select name="account_type">

                <option value="">
                    All
                </option>

                <option
                    value="FREE"
                    @selected(
                        request('account_type')
                        === 'FREE'
                    )
                >
                    Free
                </option>

                <option
                    value="PREMIUM"
                    @selected(
                        request('account_type')
                        === 'PREMIUM'
                    )
                >
                    Premium
                </option>

            </select>

        </div>


        <div class="filter-item">

            <label>
                Status
            </label>

            <select name="status">

                <option value="">
                    All
                </option>

                @foreach([
                    'PENDING',
                    'ACTIVE',
                    'SUSPENDED',
                    'BLOCKED',
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

            <label>
                Country
            </label>

            <select name="country">

                <option value="">
                    All
                </option>

                <option
                    value="LK"
                    @selected(
                        request('country')
                        === 'LK'
                    )
                >
                    Sri Lanka
                </option>

                <option
                    value="IN"
                    @selected(
                        request('country')
                        === 'IN'
                    )
                >
                    India
                </option>

            </select>

        </div>


        <div class="filter-item">

            <label>
                Sort
            </label>

            <select name="sort">

                <option
                    value="newest"
                    @selected(
                        request('sort', 'newest')
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
                    value="name_asc"
                    @selected(
                        request('sort')
                        === 'name_asc'
                    )
                >
                    Name A-Z
                </option>

                <option
                    value="name_desc"
                    @selected(
                        request('sort')
                        === 'name_desc'
                    )
                >
                    Name Z-A
                </option>

            </select>

        </div>


        <div class="filter-item">

            <label>
                Per Page
            </label>

            <select name="per_page">

                @foreach([
                    10,
                    20,
                    50,
                    100
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
                href="{{ route('admin.users.index') }}"
                class="secondary-btn"
            >
                Reset
            </a>

        </div>

    </form>

</section>


{{-- ============================================================
     USERS TABLE
============================================================ --}}

<section class="panel users-panel">

    <div class="panel-header">

        <div>

            <div class="panel-eyebrow">
                MEMBERS
            </div>

            <h2>
                User Accounts
            </h2>

        </div>


        <div class="panel-count">

            @if($users->total() > 0)

                Showing
                {{ number_format($users->firstItem()) }}
                –
                {{ number_format($users->lastItem()) }}

                of

                {{ number_format($users->total()) }}

            @else

                No users

            @endif

        </div>

    </div>


    <div class="users-table-wrap">

        <table class="users-table">

            <thead>

                <tr>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Country</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Referral</th>
                    <th>Joined</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

            @forelse($users as $user)

                <tr>

                    {{-- USER --}}
                    <td>

                        <div class="table-user">

                            <div class="table-avatar">
                                {{ strtoupper(
                                    substr(
                                        $user->name,
                                        0,
                                        1
                                    )
                                ) }}
                            </div>

                            <div>

                                <strong>
                                    {{ $user->name }}
                                </strong>

                                <span>
                                    ID #{{ $user->id }}
                                </span>

                            </div>

                        </div>

                    </td>


                    {{-- CONTACT --}}
                    <td>

                        <div class="user-contact">

                            <strong>
                                {{ $user->email }}
                            </strong>

                            <span>
                                {{ $user->whatsapp_number
                                    ?? 'No WhatsApp'
                                }}
                            </span>

                        </div>

                    </td>


                    {{-- COUNTRY --}}
                    <td>

                        {{ $user->country_code
                            ?? '—'
                        }}

                    </td>


                    {{-- ACCOUNT --}}
                    <td>

                        <span
                            class="account-badge
                            {{ $user->account_type === 'PREMIUM'
                                ? 'premium'
                                : 'free'
                            }}"
                        >

                            {{ $user->account_type }}

                        </span>

                    </td>


                    {{-- STATUS --}}
                    <td>

                        <span
                            class="user-status status-{{
                                strtolower($user->status)
                            }}"
                        >

                            <span class="user-status-dot"></span>

                            {{ $user->status }}

                        </span>

                    </td>


                    {{-- REFERRAL --}}
                    <td>

                        <code class="referral-code">

                            {{ $user->referral_code
                                ?? '—'
                            }}

                        </code>

                    </td>


                    {{-- CREATED --}}
                    <td>

                        <div class="table-date">

                            <strong>
                                {{ $user->created_at
                                    ->format('d M Y')
                                }}
                            </strong>

                            <span>
                                {{ $user->created_at
                                    ->format('H:i')
                                }}
                            </span>

                        </div>

                    </td>


                    <td>

                        <button
                            <!-- type="button"
                            class="table-action"
                            disabled
                            title="User detail comes next" -->

                             <a
                                    href="{{ route('admin.users.show', $user) }}"
                                    class="table-action"
                                    title="View User"
                            >
                                    →
                                </a>   


                        >
                            →
                        </button>

                    </td>

                </tr>

            @empty

                <tr>

                    <td
                        colspan="8"
                        class="users-empty"
                    >

                        <div class="empty-icon">
                            ◎
                        </div>

                        <strong>
                            No users found
                        </strong>

                        <span>
                            Try changing the current search or filters.
                        </span>

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>


    @include(
        'admin.components.pagination',
        [
            'paginator' => $users
        ]
    )

</section>

@endsection