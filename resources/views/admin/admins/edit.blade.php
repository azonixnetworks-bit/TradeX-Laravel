@extends('admin.layouts.app')

@section('title', 'Admin Account')
@section('page-title', 'Admin Account')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN ACCOUNT #{{ $adminUser->id }}</div>
        <h1>{{ $adminUser->name }}</h1>
        <p>{{ $adminUser->email }}</p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.administration.admins.index') }}"
    >
        Back
    </a>
</div>

@if($protected)
<section class="panel" style="padding:18px;margin-bottom:18px;">
    <strong>Protected Super Admin:</strong>
    this seeded owner account is read-only in ADMIN-13. Its role, status and identity cannot be changed through these UI endpoints.
</section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Status</span>
        <strong>{{ $adminUser->is_active ? 'ACTIVE' : 'DISABLED' }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Login Lock</span>
        <strong>{{ $adminUser->isLocked() ? 'LOCKED' : 'CLEAR' }}</strong>
        <small>{{ $adminUser->locked_until?->format('Y-m-d H:i:s') ?? '' }}</small>
    </div>
    <div class="user-stat-card">
        <span>Failed Attempts</span>
        <strong>{{ $adminUser->failed_login_attempts }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Last Login</span>
        <strong>{{ $adminUser->last_login_at?->format('Y-m-d') ?? 'Never' }}</strong>
        <small>{{ $adminUser->last_login_ip ?: '—' }}</small>
    </div>
</div>

@if($canUpdate)
<section class="panel" style="padding:22px;margin-bottom:18px;">
    <h2>Admin Profile</h2>
    <form
        method="POST"
        action="{{ route('admin.administration.admins.update', $adminUser) }}"
    >
        @csrf
        @method('PATCH')

        <div class="form-grid">
            <div class="form-group">
                <label>Name</label>
                <input
                    name="name"
                    maxlength="150"
                    value="{{ old('name', $adminUser->name) }}"
                    required
                >
            </div>
            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    maxlength="190"
                    value="{{ old('email', $adminUser->email) }}"
                    required
                >
            </div>
        </div>

        <div style="margin-top:18px;">
            <button class="primary-btn" type="submit">
                Save Profile
            </button>
        </div>
    </form>
</section>
@endif

@if($canManageRoles)
<section class="panel" style="padding:22px;margin-bottom:18px;">
    <h2>Role Assignments</h2>
    <p>
        Role changes alter effective permissions immediately. SUPER_ADMIN cannot be assigned here.
    </p>

    <form
        method="POST"
        action="{{ route('admin.administration.admins.roles', $adminUser) }}"
    >
        @csrf
        @method('PATCH')

        @php
            $selectedRoleIds = $adminUser->roles->pluck('id')->all();
        @endphp

        @foreach($assignableRoles as $role)
            <label style="display:block;margin:10px 0;">
                <input
                    type="checkbox"
                    name="role_ids[]"
                    value="{{ $role->id }}"
                    @checked(in_array($role->id, old('role_ids', $selectedRoleIds)))
                >
                <strong>{{ $role->name }}</strong>
                <small>
                    {{ $role->code }}
                    {{ $role->is_system ? '· SYSTEM' : '· CUSTOM' }}
                </small>
            </label>
        @endforeach

        <button class="primary-btn" type="submit">
            Update Roles
        </button>
    </form>
</section>
@endif

@if($canManageStatus)
<section class="panel" style="padding:22px;">
    <h2>Account Status</h2>
    <p>
        Disabling an admin blocks future password login. Existing browser-session invalidation is not added in ADMIN-13 and remains a separate hardening decision.
    </p>

    <form
        method="POST"
        action="{{ route('admin.administration.admins.status', $adminUser) }}"
    >
        @csrf
        @method('PATCH')

        <div class="form-grid">
            <div class="form-group">
                <label>New Status</label>
                <select name="status" required>
                    <option value="ACTIVE" @selected($adminUser->is_active)>ACTIVE</option>
                    <option value="DISABLED" @selected(!$adminUser->is_active)>DISABLED</option>
                </select>
            </div>

            <div class="form-group">
                <label>Reason</label>
                <input
                    name="reason"
                    maxlength="500"
                    required
                    placeholder="Reason for this status decision"
                >
            </div>
        </div>

        <button class="primary-btn" type="submit">
            Apply Status
        </button>
    </form>
</section>
@endif
@endsection
