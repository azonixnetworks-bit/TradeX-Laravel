@extends('admin.layouts.app')

@section('title', 'Integrations')
@section('page-title', 'Integrations')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">EXTERNAL PROVIDER CONTROL PLANE</div>
        <h1>Integrations <span>Management</span></h1>
        <p>
            Payment-provider readiness, merchant eligibility, country scope and
            runtime ownership without exposing credentials.
        </p>
    </div>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Providers</span>
        <strong>{{ $summary['total'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Configured</span>
        <strong>{{ $summary['configured'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Control Enabled</span>
        <strong>{{ $summary['control_enabled'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Existing Runtime Adapters</span>
        <strong>{{ $summary['runtime_existing'] }}</strong>
    </div>
</div>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Security boundary:</strong>
    credentials, webhook secrets, API keys and bank account numbers are never
    rendered or editable here. ADMIN-16 stores only safe control-plane state.
</section>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Runtime boundary:</strong>
    PayPal and Local Bank already have existing Payments API adapters.
    Stripe and NOWPayments are registered and readiness-managed here, but
    customer checkout/capture remains unavailable until a dedicated Payments
    API provider extension is implemented and regression-tested.
</section>

<div
    style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
        gap:18px;
    "
>
@foreach($providers as $provider)
    <section
        class="panel"
        style="padding:20px;"
    >
        <div class="panel-eyebrow">
            {{ $provider['kind'] }}
        </div>

        <h2 style="margin-top:8px;">
            {{ $provider['display_name'] }}
        </h2>

        <table
            class="users-table"
            style="margin-top:14px;"
        >
            <tbody>
                <tr>
                    <th>Config</th>
                    <td>
                        {{ $provider['configured'] ? 'READY' : 'INCOMPLETE' }}
                    </td>
                </tr>
                <tr>
                    <th>Control</th>
                    <td>{{ $provider['control_status'] }}</td>
                </tr>
                <tr>
                    <th>Runtime</th>
                    <td>{{ $provider['runtime_status'] }}</td>
                </tr>
                <tr>
                    <th>Countries</th>
                    <td>
                        {{ $provider['countries'] === []
                            ? 'None approved'
                            : implode(', ', $provider['countries']) }}
                    </td>
                </tr>
                <tr>
                    <th>Environment</th>
                    <td>{{ $provider['environment'] }}</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top:18px;">
            <a
                class="secondary-btn"
                href="{{ route(
                    'admin.integrations.show',
                    $provider['code']
                ) }}"
            >
                Inspect
            </a>
        </div>
    </section>
@endforeach
</div>
@endsection
