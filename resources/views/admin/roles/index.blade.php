@extends('admin.layouts.app')

@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">RBAC ADMINISTRATION</div>
        <h1>Roles & <span>Permissions</span></h1>
        <p>
            System roles are seeded and immutable. Custom roles can be created and managed without changing the permission catalog itself.
        </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a
            class="secondary-btn"
            href="{{ route('admin.administration.permissions.index') }}"
        >
            Permission Catalog
        </a>

        @if(auth('admin')->user()->hasPermission('roles.manage'))
            <a
                class="primary-btn"
                href="{{ route('admin.administration.roles.create') }}"
            >
                Create Custom Role
            </a>
        @endif
    </div>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Total Roles</span>
        <strong>{{ $stats['total'] }}</strong>
    </div>
    <div class="user-stat-card">
        <span>System Roles</span>
        <strong>{{ $stats['system'] }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Custom Roles</span>
        <strong>{{ $stats['custom'] }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Active Roles</span>
        <strong>{{ $stats['active'] }}</strong>
    </div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ROLE INVENTORY</div>
            <h2>Admin Roles</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Admins</th>
                    <th>Permissions</th>
                    <th>Description</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($roles as $role)
                <tr>
                    <td>
                        <strong>{{ $role->name }}</strong><br>
                        <small>{{ $role->code }}</small>
                    </td>
                    <td>
                        {{ $role->is_system ? 'SYSTEM' : 'CUSTOM' }}
                        @if($role->code === 'SUPER_ADMIN')
                            <br><strong>PROTECTED</strong>
                        @endif
                    </td>
                    <td>{{ $role->is_active ? 'ACTIVE' : 'DISABLED' }}</td>
                    <td>{{ $role->admins_count }}</td>
                    <td>{{ $role->permissions_count }}</td>
                    <td>{{ $role->description ?: '—' }}</td>
                    <td>
                        <a
                            class="secondary-btn"
                            href="{{ route('admin.administration.roles.permissions', $role) }}"
                        >
                            {{ $role->is_system ? 'Inspect' : 'Manage' }}
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
