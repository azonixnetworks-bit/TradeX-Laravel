@extends('admin.layouts.app')

@section('title', 'Create Custom Role')
@section('page-title', 'Create Custom Role')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">RBAC ADMINISTRATION</div>
        <h1>Create <span>Role</span></h1>
        <p>
            Custom roles use the existing immutable permission catalog. SUPER_ADMIN cannot be recreated.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.administration.roles.index') }}"
    >
        Back
    </a>
</div>

<form
    method="POST"
    action="{{ route('admin.administration.roles.store') }}"
>
    @csrf

    <section class="panel" style="padding:22px;margin-bottom:18px;">
        <div class="form-grid">
            <div class="form-group">
                <label>Role Code</label>
                <input
                    name="code"
                    maxlength="60"
                    value="{{ old('code') }}"
                    placeholder="EXAMPLE_ROLE"
                    required
                >
                <small>Uppercase A-Z, numbers and underscores. Immutable after creation.</small>
            </div>

            <div class="form-group">
                <label>Name</label>
                <input
                    name="name"
                    maxlength="120"
                    value="{{ old('name') }}"
                    required
                >
            </div>

            <div class="form-group">
                <label>Description</label>
                <input
                    name="description"
                    maxlength="500"
                    value="{{ old('description') }}"
                >
            </div>
        </div>
    </section>

    <section class="panel" style="padding:22px;">
        <h2>Permissions</h2>
        <p>Select at least one permission.</p>

        @foreach($permissions as $module => $items)
            <div style="margin:20px 0;">
                <h3>{{ $module }}</h3>

                @foreach($items as $permission)
                    <label style="display:block;margin:8px 0;">
                        <input
                            type="checkbox"
                            name="permission_ids[]"
                            value="{{ $permission->id }}"
                            @checked(in_array($permission->id, old('permission_ids', [])))
                        >
                        <strong>{{ $permission->name }}</strong>
                        <small>{{ $permission->code }}</small>
                    </label>
                @endforeach
            </div>
        @endforeach

        <button class="primary-btn" type="submit">
            Create Role
        </button>
    </section>
</form>
@endsection
