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
                        {{ in_array('*', $provider['source_runtime_countries'], true)
                            ? 'All Countries'
                            : ($provider['source_runtime_countries'] === []
                                ? 'None'
                                : implode(', ', $provider['source_runtime_countries'])) }}
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
<div class="admin-form-callout gold">
    <div class="admin-form-callout-icon" aria-hidden="true">◆</div>
    <div>
        <strong>Control-plane only</strong>
        This records authorized provider approval and country scope. It does
        not rewrite <code>.env</code>, expose credentials, or settle payments.
    </div>
</div>

<section class="panel admin-form-panel">
    <div class="admin-form-heading">
        <div class="panel-eyebrow">ADMIN CONTROL-PLANE</div>
        <h2>Provider approval state</h2>
        <p>
            Review the server configuration above before changing approval,
            country scope, environment or merchant eligibility state.
        </p>
    </div>

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
                <label for="integration_is_enabled">Control Enabled</label>
                <select
                    id="integration_is_enabled"
                    name="is_enabled"
                >
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
                <small class="form-help">
                    Approval remains subject to server configuration and provider eligibility.
                </small>
                @error('is_enabled')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="integration_environment">Environment</label>
                <select
                    id="integration_environment"
                    name="environment"
                >
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
                <small class="form-help">
                    Must match the provider environment configured on the server.
                </small>
                @error('environment')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="integration_countries">Approved Countries</label>
                <input
                    id="integration_countries"
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
                    autocomplete="off"
                >
                <small class="form-help">
                    Use ISO 2-letter codes separated by commas. PayPal, Stripe
                    and NOWPayments accept <code>*</code> for All Countries.
                    Provider/merchant eligibility must be checked before approval.
                </small>
                @error('countries')
                    <small class="form-error">{{ $message }}</small>
                @enderror
                @error('countries.*')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="integration_eligibility">
                    Merchant / Provider Eligibility Confirmed
                </label>
                <select
                    id="integration_eligibility"
                    name="eligibility_confirmed"
                >
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
                <small class="form-help">
                    Confirm only after the merchant/provider account is genuinely eligible.
                </small>
                @error('eligibility_confirmed')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="integration_change_reason">Change Reason</label>
                <textarea
                    id="integration_change_reason"
                    name="change_reason"
                    maxlength="500"
                    rows="4"
                    required
                    placeholder="Record why this provider control state is being changed."
                >{{ old('change_reason') }}</textarea>
                <small class="form-help">
                    The audit event records that a reason was provided, but does
                    not copy this free text into the audit payload.
                </small>
                @error('change_reason')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>
        </div>

        <div class="admin-form-actions">
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
