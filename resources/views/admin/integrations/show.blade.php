@extends('admin.layouts.app')

@section('title', $provider['display_name'].' Integration')
@section('page-title', $provider['display_name'].' Integration')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            {{ $provider['code'] }} · {{ $provider['kind'] }}
        </div>

        <h1>
            {{ $provider['display_name'] }}
            <span>Integration</span>
        </h1>

        <p>
            {{ $provider['transaction_owner'] }}
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.integrations.index') }}"
    >
        Back
    </a>
</div>

@if(session('success'))
    <section
        class="panel"
        style="padding:14px;margin-bottom:18px;"
    >
        {{ session('success') }}
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Configuration</span>
        <strong>
            {{ $provider['configured'] ? 'READY' : 'INCOMPLETE' }}
        </strong>
    </div>

    <div class="user-stat-card">
        <span>Control State</span>
        <strong>{{ $provider['control_status'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Runtime State</span>
        <strong>{{ $provider['runtime_status'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Adapter</span>
        <strong>{{ $provider['runtime_adapter_status'] }}</strong>
    </div>
</div>

@if($provider['runtime_adapter_status'] !== 'ACTIVE_EXISTING')
<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Not a live checkout claim:</strong>
    ADMIN-16 can approve and verify server-side readiness for this provider,
    but no customer transaction adapter is wired in the frozen Payments API yet.
</section>
@endif

<section
    class="panel users-panel"
    style="margin-bottom:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">READINESS CHECKS</div>
            <h2>Server Configuration</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Check</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($provider['configuration_checks'] as $label => $ready)
                <tr>
                    <td>{{ $label }}</td>
                    <td>
                        <strong>
                            {{ $ready ? 'CONFIGURED' : 'MISSING' }}
                        </strong>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

<section
    class="panel users-panel"
    style="margin-bottom:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CURRENT SOURCE RUNTIME</div>
            <h2>Environment-Owned Status</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
                <tr>
                    <th>Source Enabled</th>
                    <td>
                        {{ $provider['source_runtime_enabled'] ? 'YES' : 'NO' }}
                    </td>
                </tr>
                <tr>
                    <th>Source Countries</th>
                    <td>
                        {{ $provider['source_runtime_countries'] === []
                            ? 'None'
                            : implode(', ', $provider['source_runtime_countries']) }}
                    </td>
                </tr>
                <tr>
                    <th>Source Environment</th>
                    <td>{{ $provider['source_runtime_environment'] }}</td>
                </tr>
                <tr>
                    <th>Secrets</th>
                    <td>Never displayed</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@if($canManage)
<section
    class="panel"
    style="padding:22px;"
>
    <h2>Admin Control-Plane State</h2>

    <p>
        This records authorized provider approval and country scope.
        It does not rewrite <code>.env</code> and does not expose credentials.
    </p>

    <form
        method="POST"
        action="{{ route(
            'admin.integrations.update',
            $provider['code']
        ) }}"
    >
        @csrf
        @method('PATCH')

        <input
            type="hidden"
            name="version"
            value="{{ $provider['version'] }}"
        >

        <div class="form-grid">
            <div class="form-group">
                <label>Control Enabled</label>
                <select name="is_enabled">
                    <option
                        value="0"
                        @selected(
                            ! old(
                                'is_enabled',
                                $provider['control_enabled']
                            )
                        )
                    >
                        Disabled
                    </option>
                    <option
                        value="1"
                        @selected(
                            (bool) old(
                                'is_enabled',
                                $provider['control_enabled']
                            )
                        )
                    >
                        Enabled / Approved
                    </option>
                </select>
                @error('is_enabled')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Environment</label>
                <select name="environment">
                    @foreach($provider['environment_options'] as $environment)
                        <option
                            value="{{ $environment }}"
                            @selected(
                                old(
                                    'environment',
                                    $provider['environment']
                                ) === $environment
                            )
                        >
                            {{ $environment }}
                        </option>
                    @endforeach
                </select>
                @error('environment')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Approved Countries</label>
                <input
                    type="text"
                    name="countries"
                    maxlength="200"
                    value="{{ old(
                        'countries',
                        implode(
                            ',',
                            $provider['countries']
                        )
                    ) }}"
                    placeholder="LK,IN"
                >
                <small>
                    ISO 2-letter codes only. Provider/merchant eligibility must
                    be checked before approval.
                </small>
                @error('countries')
                    <small>{{ $message }}</small>
                @enderror
                @error('countries.*')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Merchant / Provider Eligibility Confirmed</label>
                <select name="eligibility_confirmed">
                    <option
                        value="0"
                        @selected(
                            ! old(
                                'eligibility_confirmed',
                                $provider['eligibility_confirmed']
                            )
                        )
                    >
                        Not Confirmed
                    </option>
                    <option
                        value="1"
                        @selected(
                            (bool) old(
                                'eligibility_confirmed',
                                $provider['eligibility_confirmed']
                            )
                        )
                    >
                        Confirmed
                    </option>
                </select>
                @error('eligibility_confirmed')
                    <small>{{ $message }}</small>
                @enderror
            </div>
        </div>

        <div
            class="form-group"
            style="margin-top:18px;"
        >
            <label>Change Reason</label>
            <textarea
                name="change_reason"
                maxlength="500"
                rows="4"
                required
            >{{ old('change_reason') }}</textarea>
            <small>
                The audit event records that a reason was provided, but does
                not copy free text into the audit payload.
            </small>
            @error('change_reason')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div style="margin-top:20px;">
            <button
                class="primary-btn"
                type="submit"
            >
                Save Integration State
            </button>
        </div>
    </form>
</section>
@endif
@endsection
