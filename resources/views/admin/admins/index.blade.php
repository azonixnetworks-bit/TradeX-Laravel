@extends('admin.layouts.app')

@section('title', 'Admin Users')
@section('page-title', 'Admin Users')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SYSTEM ADMINISTRATION</div>
        <h1>Admin <span>Users</span></h1>
        <p>
            Manage delegated TradeX administrator accounts without exposing the seeded Super Admin to UI takeover.
        </p>
    </div>

    @if(
        auth('admin')->user()->hasPermission('admins.create')
        && auth('admin')->user()->hasPermission('roles.manage')
    )
        <a
            class="primary-btn"
            href="{{ route('admin.administration.admins.create') }}"
        >
            Create Admin
        </a>
    @endif
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Total Admins</span>
        <strong>{{ number_format($stats['total']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Active</span>
        <strong>{{ number_format($stats['active']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Disabled</span>
        <strong>{{ number_format($stats['disabled']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Temporarily Locked</span>
        <strong>{{ number_format($stats['locked']) }}</strong>
    </div>
</div>

<section class="panel user-filter-panel">
    <form
        class="user-filter-form"
        method="GET"
        action="{{ route('admin.administration.admins.index') }}"
    >
        <div class="filter-item">
            <label>Search</label>
            <input
                type="text"
                name="search"
                maxlength="190"
                value="{{ request('search') }}"
                placeholder="Name, email or UUID"
            >
        </div>

        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach(['ACTIVE','DISABLED','LOCKED'] as $status)
                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Role</label>
            <select name="role">
                <option value="">All</option>
                @foreach($roles as $role)
                    <option
                        value="{{ $role->code }}"
                        @selected(request('role') === $role->code)
                    >
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50,100] as $size)
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
            <button class="primary-btn" type="submit">
                Apply
            </button>
            <a
                class="secondary-btn"
                href="{{ route('admin.administration.admins.index') }}"
            >
                Reset
            </a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ADMIN ACCOUNTS</div>
            <h2>Administrators</h2>
        </div>
        <div class="panel-count">
            {{ number_format($admins->total()) }} records
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Admin</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Last IP</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($admins as $adminUser)
                <tr>
                    <td>
                        <strong>{{ $adminUser->name }}</strong><br>
                        <small>{{ $adminUser->email }}</small><br>
                        <small>{{ $adminUser->uuid }}</small>
                    </td>
                    <td>
                        @foreach($adminUser->roles as $role)
                            <div>
                                {{ $role->name }}
                                @if($role->code === 'SUPER_ADMIN')
                                    <strong>PROTECTED</strong>
                                @elseif(!$role->is_active)
                                    <small>INACTIVE</small>
                                @endif
                            </div>
                        @endforeach
                    </td>
                    <td>
                        @if(!$adminUser->is_active)
                            <strong>DISABLED</strong>
                        @elseif($adminUser->isLocked())
                            <strong>LOCKED</strong>
                        @else
                            <strong>ACTIVE</strong>
                        @endif
                    </td>
                    <td>
                        {{ $adminUser->last_login_at?->format('Y-m-d H:i:s') ?? 'Never' }}
                    </td>
                    <td>{{ $adminUser->last_login_ip ?: '—' }}</td>
                    <td>{{ $adminUser->created_at?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        <a
                            class="secondary-btn"
                            href="{{ route('admin.administration.admins.edit', $adminUser) }}"
                        >
                            Inspect
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        No admin accounts match the filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">
        {{ $admins->links() }}
    </div>
</section>
@endsection
