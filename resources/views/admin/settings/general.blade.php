@extends('admin.layouts.app')

@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SAFE OPERATIONAL REGISTRY</div>
        <h1>General <span>Settings</span></h1>
        <p>
            Only explicitly allowlisted, non-secret TradeX operational values can be stored here.
        </p>
    </div>
</div>

@include('admin.settings.partials.nav')

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Security boundary:</strong>
    this page never rewrites `.env` and cannot edit APP_KEY, API credentials,
    payment destinations, cryptographic keys, SMTP credentials, private storage
    paths or infrastructure secrets.
</section>

@if(session('success'))
    <section
        class="panel"
        style="padding:14px;margin-bottom:18px;"
    >
        {{ session('success') }}
    </section>
@endif

<section
    class="panel"
    style="padding:22px;"
>
    @if($canManage)
        <form
            method="POST"
            action="{{ route('admin.settings.general.update') }}"
        >
            @csrf
            @method('PATCH')

            @foreach($versions as $field => $version)
                <input
                    type="hidden"
                    name="versions[{{ $field }}]"
                    value="{{ $version }}"
                >
            @endforeach

            <div class="form-grid">
                <div class="form-group">
                    <label>Platform Display Name</label>
                    <input
                        type="text"
                        name="platform_name"
                        maxlength="80"
                        value="{{ old('platform_name', $values['platform_name']) }}"
                        required
                    >
                    @error('platform_name')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Support Email</label>
                    <input
                        type="email"
                        name="support_email"
                        maxlength="190"
                        value="{{ old('support_email', $values['support_email']) }}"
                        placeholder="support@example.com"
                    >
                    @error('support_email')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Support WhatsApp</label>
                    <input
                        type="text"
                        name="support_whatsapp"
                        maxlength="20"
                        value="{{ old('support_whatsapp', $values['support_whatsapp']) }}"
                        placeholder="+94771234567"
                    >
                    <small>E.164 international format only.</small>
                    @error('support_whatsapp')
                        <small>{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <div style="margin-top:20px;">
                <input
                    type="hidden"
                    name="maintenance_notice_enabled"
                    value="0"
                >

                <label>
                    <input
                        type="checkbox"
                        name="maintenance_notice_enabled"
                        value="1"
                        @checked(
                            old(
                                'maintenance_notice_enabled',
                                $values['maintenance_notice_enabled']
                            )
                        )
                    >
                    <strong>Enable maintenance notice registry value</strong>
                </label>

                @error('maintenance_notice_enabled')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div
                class="form-group"
                style="margin-top:16px;"
            >
                <label>Maintenance Notice</label>
                <textarea
                    name="maintenance_notice"
                    maxlength="500"
                    rows="4"
                    placeholder="Short user-facing maintenance notice"
                >{{ old('maintenance_notice', $values['maintenance_notice']) }}</textarea>

                @error('maintenance_notice')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div style="margin-top:20px;">
                <button
                    class="primary-btn"
                    type="submit"
                >
                    Save General Settings
                </button>
            </div>
        </form>
    @else
        <div class="users-table-wrap">
            <table class="users-table">
                <tbody>
                    <tr>
                        <th>Platform Display Name</th>
                        <td>{{ $values['platform_name'] }}</td>
                    </tr>
                    <tr>
                        <th>Support Email</th>
                        <td>{{ $values['support_email'] ?: 'Not configured' }}</td>
                    </tr>
                    <tr>
                        <th>Support WhatsApp</th>
                        <td>{{ $values['support_whatsapp'] ?: 'Not configured' }}</td>
                    </tr>
                    <tr>
                        <th>Maintenance Notice Enabled</th>
                        <td>{{ $values['maintenance_notice_enabled'] ? 'YES' : 'NO' }}</td>
                    </tr>
                    <tr>
                        <th>Maintenance Notice</th>
                        <td>{{ $values['maintenance_notice'] ?: 'Not configured' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</section>

<section
    class="panel"
    style="padding:18px;margin-top:18px;"
>
    <strong>Consumer boundary:</strong>
    these values form the central safe settings registry. A frontend/API feature
    uses a value only after that owning module is explicitly wired to this registry.
    ADMIN-14 does not silently override frozen module configuration.
</section>
@endsection
