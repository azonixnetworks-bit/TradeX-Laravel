@extends('admin.layouts.app')

@section('title', 'TradeX Customers')
@section('page-title', 'Customers')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">CUSTOMER MANAGEMENT</div>
        <h1>TradeX <span>Customers</span></h1>
        <p>
            {{ $segmentLabel }} — search, filter and monitor registered TradeX customer accounts.
            @if($segment === 'RECENT')
                Recent means joined within the last {{ $recentDays }} days.
            @endif
        </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a
            class="secondary-btn"
            href="{{ route('admin.customers.dashboard') }}"
        >
            Customer Dashboard
        </a>

        @if(auth('admin')->user()?->hasPermission('users.invite'))
            <a
                class="primary-btn"
                href="{{ route('admin.customers.create') }}"
            >
                Add Customer
            </a>
        @endif
    </div>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Total Customers</span>
        <strong>{{ number_format($stats['total']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Active</span>
        <strong class="text-green">{{ number_format($stats['active']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Free Customers</span>
        <strong>{{ number_format($stats['free']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Premium</span>
        <strong class="text-gold">{{ number_format($stats['premium']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Suspended</span>
        <strong class="text-orange">{{ number_format($stats['suspended']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Blocked</span>
        <strong class="text-red">{{ number_format($stats['blocked']) }}</strong>
    </div>
</div>

<section class="panel user-filter-panel">
    <form
        method="GET"
        action="{{ route('admin.users.index') }}"
        class="user-filter-form"
    >
        @if($segment)
            <input type="hidden" name="segment" value="{{ $segment }}">
        @endif

        <div class="filter-search">
            <label>Search</label>
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Name, email, mobile number or referral code"
            >
        </div>

        <div class="filter-item">
            <label>Account</label>
            <select name="account_type">
                <option value="">All</option>
                <option value="FREE" @selected(request('account_type') === 'FREE')>
                    Free
                </option>
                <option value="PREMIUM" @selected(request('account_type') === 'PREMIUM')>
                    Premium
                </option>
            </select>
        </div>

        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach(['PENDING', 'ACTIVE', 'SUSPENDED', 'BLOCKED'] as $statusOption)
                    <option
                        value="{{ $statusOption }}"
                        @selected(request('status') === $statusOption)
                    >
                        {{ ucfirst(strtolower($statusOption)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Country</label>
            <select name="country">
                <option value="">All</option>
                <option value="LK" @selected(request('country') === 'LK')>Sri Lanka</option>
                <option value="IN" @selected(request('country') === 'IN')>India</option>
                <option value="AE" @selected(request('country') === 'AE')>UAE</option>
                <option value="MY" @selected(request('country') === 'MY')>Malaysia</option>
            </select>
        </div>

        <div class="filter-item">
            <label>Sort</label>
            <select name="sort">
                <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>Oldest</option>
                <option value="name_asc" @selected(request('sort') === 'name_asc')>Name A-Z</option>
                <option value="name_desc" @selected(request('sort') === 'name_desc')>Name Z-A</option>
            </select>
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10, 20, 50, 100] as $size)
                    <option
                        value="{{ $size }}"
                        @selected((int) request('per_page', 20) === $size)
                    >
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="primary-btn">Apply</button>

            <a
                href="{{ $segment
                    ? route('admin.users.index', ['segment' => $segment])
                    : route('admin.users.index') }}"
                class="secondary-btn"
            >
                Reset
            </a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">{{ strtoupper($segmentLabel) }}</div>
            <h2>Customer Accounts</h2>
        </div>

        <div class="panel-count">
            @if($users->total() > 0)
                Showing {{ number_format($users->firstItem()) }}–{{ number_format($users->lastItem()) }}
                of {{ number_format($users->total()) }}
            @else
                No customers
            @endif
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Customer</th>
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
                    <td>
                        <div class="table-user">
                            <div class="table-avatar">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <strong>{{ $user->name }}</strong>
                                <span>ID #{{ $user->id }}</span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="user-contact">
                            <strong>{{ $user->email }}</strong>
                            <span>{{ $user->whatsapp_number ?? 'No WhatsApp' }}</span>
                        </div>
                    </td>

                    <td>{{ $user->country_code ?? '—' }}</td>

                    <td>
                        <span
                            class="account-badge {{ $user->account_type === 'PREMIUM' ? 'premium' : 'free' }}"
                        >
                            {{ $user->account_type }}
                        </span>
                    </td>

                    <td>
                        <span class="user-status status-{{ strtolower($user->status) }}">
                            <span class="user-status-dot"></span>
                            {{ $user->status }}
                        </span>
                    </td>

                    <td>
                        <code class="referral-code">{{ $user->referral_code ?? '—' }}</code>
                    </td>

                    <td>
                        <div class="table-date">
                            <strong>{{ $user->created_at->format('d M Y') }}</strong>
                            <span>{{ $user->created_at->format('H:i') }}</span>
                        </div>
                    </td>

                    <td>
                        <a
                            href="{{ route('admin.users.show', $user) }}"
                            class="table-action"
                            title="View customer"
                        >
                            →
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="users-empty">
                        <div class="empty-icon">◎</div>
                        <strong>No customers found</strong>
                        <span>Try changing the current search, segment or filters.</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include(
        'admin.components.pagination',
        ['paginator' => $users]
    )
</section>
@endsection
