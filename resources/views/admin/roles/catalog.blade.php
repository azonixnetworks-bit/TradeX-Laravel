@extends('admin.layouts.app')

@section('title', 'Permission Catalog')
@section('page-title', 'Permission Catalog')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">READ-ONLY RBAC CATALOG</div>
        <h1>Permission <span>Catalog</span></h1>
        <p>
            Permissions are source-controlled capabilities. ADMIN-13 does not create, rename or delete permission codes.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.administration.roles.index') }}"
    >
        Back to Roles
    </a>
</div>

@foreach($permissionGroups as $module => $permissions)
<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">{{ $module }}</div>
            <h2>{{ count($permissions) }} Permissions</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            @foreach($permissions as $permission)
                <tr>
                    <td><strong>{{ $permission->code }}</strong></td>
                    <td>{{ $permission->name }}</td>
                    <td>{{ $permission->description ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endforeach
@endsection
