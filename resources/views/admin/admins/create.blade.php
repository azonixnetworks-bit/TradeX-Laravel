@extends('admin.layouts.app')

@section('title', 'Create Admin')
@section('page-title', 'Create Admin')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SYSTEM ADMINISTRATION</div>
        <h1>Create <span>Admin</span></h1>
        <p>
            New administrator passwords are accepted once and are never displayed, emailed or written to audit logs.
        </p>
    </div>

    <a class="secondary-btn" href="{{ route('admin.administration.admins.index') }}">Back</a>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <strong>Invitation email:</strong>
    after the account is created, TradeX attempts to send a security invitation to the admin email address. The Admin Users screen records whether the configured mail transport accepted or failed the message. Final inbox delivery remains unconfirmed unless a transactional mail provider supplies delivery webhooks.
</section>

<section class="panel" style="padding:22px;">
    <form method="POST" action="{{ route('admin.administration.admins.store') }}">
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label>Name</label>
                <input name="name" value="{{ old('name') }}" maxlength="150" required>
                @error('name')<small>{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" maxlength="190" required>
                @error('email')<small>{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label>Initial Password</label>
                <input type="password" name="password" autocomplete="new-password" required>
                <small>Minimum 16 characters with upper/lower case, number and symbol. Share it only through an approved secure channel.</small>
                @error('password')<small>{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" autocomplete="new-password" required>
            </div>
        </div>

        <div style="margin-top:20px;">
            <h3>Assign Roles</h3>
            <p>SUPER_ADMIN is intentionally unavailable. Assign at least one active delegated role.</p>

            @foreach($roles as $role)
                <label style="display:block;margin:10px 0;">
                    <input
                        type="checkbox"
                        name="role_ids[]"
                        value="{{ $role->id }}"
                        @checked(in_array($role->id, old('role_ids', [])))
                    >
                    <strong>{{ $role->name }}</strong>
                    <small>{{ $role->code }} {{ $role->is_system ? '· SYSTEM' : '· CUSTOM' }}</small>
                </label>
            @endforeach

            @error('role_ids')<small>{{ $message }}</small>@enderror
            @error('role_ids.*')<small>{{ $message }}</small>@enderror
        </div>

        <div style="margin-top:22px;">
            <button class="primary-btn" type="submit">Create Admin + Send Invitation</button>
        </div>
    </form>
</section>
@endsection
