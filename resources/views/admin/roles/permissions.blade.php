@extends('admin.layouts.app')

@section('title', 'Role Permissions')
@section('page-title', 'Role Permissions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $role->is_system ? 'SYSTEM ROLE' : 'CUSTOM ROLE' }}</div>
        <h1>{{ $role->name }}</h1>
        <p>{{ $role->code }}</p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.administration.roles.index') }}"
    >
        Back
    </a>
</div>

@if($protected)
<section class="panel" style="padding:18px;margin-bottom:18px;">
    <strong>Immutable system role:</strong>
    metadata, activation state and permission assignments are controlled by source-controlled seeders and are read-only here.
</section>
@endif

@if($canManage)
<section class="panel" style="padding:22px;margin-bottom:18px;">
    <h2>Role Metadata</h2>
    <form
        method="POST"
        action="{{ route('admin.administration.roles.update', $role) }}"
    >
        @csrf
        @method('PATCH')

        <div class="form-grid">
            <div class="form-group">
                <label>Code</label>
                <input value="{{ $role->code }}" disabled>
                <small>Role code is immutable.</small>
            </div>
            <div class="form-group">
                <label>Name</label>
                <input
                    name="name"
                    maxlength="120"
                    value="{{ old('name', $role->name) }}"
                    required
                >
            </div>
            <div class="form-group">
                <label>Description</label>
                <input
                    name="description"
                    maxlength="500"
                    value="{{ old('description', $role->description) }}"
                >
            </div>
        </div>

        <button class="primary-btn" type="submit">
            Save Metadata
        </button>
    </form>
</section>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <h2>Role Status</h2>
    <form
        method="POST"
        action="{{ route('admin.administration.roles.status', $role) }}"
    >
        @csrf
        @method('PATCH')

        <div class="form-grid">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ACTIVE" @selected($role->is_active)>ACTIVE</option>
                    <option value="DISABLED" @selected(!$role->is_active)>DISABLED</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason</label>
                <input
                    name="reason"
                    maxlength="500"
                    required
                    placeholder="Reason for status decision"
                >
            </div>
        </div>

        <button class="primary-btn" type="submit">
            Apply Status
        </button>
    </form>
</section>
@endif

<section class="panel" style="padding:22px;">
    <h2>Permission Matrix</h2>

    @php
        $selected = $role->permissions->pluck('id')->all();
    @endphp

    @if($canManage)
        <form
            method="POST"
            action="{{ route('admin.administration.roles.permissions.update', $role) }}"
        >
            @csrf
            @method('PATCH')
    @endif

    @foreach($permissionGroups as $module => $items)
        <div style="margin:20px 0;">
            <h3>{{ $module }}</h3>

            @foreach($items as $permission)
                <label style="display:block;margin:8px 0;">
                    <input
                        type="checkbox"
                        name="permission_ids[]"
                        value="{{ $permission->id }}"
                        @checked(in_array($permission->id, old('permission_ids', $selected)))
                        @disabled(!$canManage)
                    >
                    <strong>{{ $permission->name }}</strong>
                    <small>{{ $permission->code }}</small>
                </label>
            @endforeach
        </div>
    @endforeach

    @if($canManage)
        <button class="primary-btn" type="submit">
            Save Permissions
        </button>
        </form>
    @endif
</section>
@endsection
