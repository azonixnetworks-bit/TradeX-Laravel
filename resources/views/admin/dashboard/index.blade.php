@extends('admin.layouts.app')

@section('title', 'TradeX Admin Dashboard')

@section('page-title', 'Dashboard')


@section('content')

<div class="page-header">

    <div>

        <div class="page-eyebrow">
            TRADEX ADMINISTRATION
        </div>

        <h1>
            Welcome back,
            <span>
                {{ $admin->name }}
            </span>
        </h1>

        <p>
            Monitor the TradeX platform from one secure workspace.
        </p>

    </div>

    <div class="page-date">
        {{ now()->format('d M Y') }}
    </div>

</div>


<div class="stat-grid">

    <div class="stat-card">

        <div class="stat-icon blue">
            ◎
        </div>

        <div class="stat-content">

            <span>
                Admin Users
            </span>

            <strong>
                {{ number_format($stats['admins']) }}
            </strong>

            <small>
                Registered administrators
            </small>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon green">
            ✓
        </div>

        <div class="stat-content">

            <span>
                Active Admins
            </span>

            <strong>
                {{ number_format($stats['active_admins']) }}
            </strong>

            <small>
                Enabled accounts
            </small>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon gold">
            ♛
        </div>

        <div class="stat-content">

            <span>
                Admin Roles
            </span>

            <strong>
                {{ number_format($stats['roles']) }}
            </strong>

            <small>
                RBAC roles configured
            </small>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon purple">
            ⚿
        </div>

        <div class="stat-content">

            <span>
                Permissions
            </span>

            <strong>
                {{ number_format($stats['permissions']) }}
            </strong>

            <small>
                Security permissions
            </small>

        </div>

    </div>

</div>


<div class="dashboard-grid">

    <section class="panel quick-panel">

        <div class="panel-header">

            <div>
                <div class="panel-eyebrow">
                    QUICK ACCESS
                </div>

                <h2>
                    Platform Management
                </h2>
            </div>

        </div>


        <div class="quick-grid">

            @if($admin->hasPermission('users.view'))

                <div class="quick-card">

                    <div class="quick-icon">
                        ◎
                    </div>

                    <div>
                        <strong>
                            Users
                        </strong>

                        <span>
                            Manage TradeX members
                        </span>
                    </div>

                    <div class="soon-tag">
                        ADMIN-02
                    </div>

                </div>

            @endif


            @if($admin->hasPermission('education.view'))

                <div class="quick-card">

                    <div class="quick-icon">
                        ▣
                    </div>

                    <div>
                        <strong>
                            Education
                        </strong>

                        <span>
                            Courses & lessons
                        </span>
                    </div>

                    <div class="soon-tag">
                        ADMIN-03
                    </div>

                </div>

            @endif


            @if($admin->hasPermission('trade_ideas.view'))

                <div class="quick-card">

                    <div class="quick-icon">
                        ◇
                    </div>

                    <div>
                        <strong>
                            Trade Ideas
                        </strong>

                        <span>
                            Forex, Crypto, Deriv & Stocks
                        </span>
                    </div>

                    <div class="soon-tag">
                        ADMIN-05
                    </div>

                </div>

            @endif


            @if($admin->hasPermission('payments.view'))

                <div class="quick-card">

                    <div class="quick-icon">
                        $
                    </div>

                    <div>
                        <strong>
                            Payments
                        </strong>

                        <span>
                            PayPal, Bank & USDT
                        </span>
                    </div>

                    <div class="soon-tag">
                        ADMIN-08
                    </div>

                </div>

            @endif

        </div>

    </section>


    <section class="panel security-panel">

        <div class="panel-header">

            <div>

                <div class="panel-eyebrow">
                    SECURITY
                </div>

                <h2>
                    Administration Status
                </h2>

            </div>

        </div>


        <div class="security-status-row">

            <span>
                Admin Guard
            </span>

            <strong class="status-success">
                ACTIVE
            </strong>

        </div>


        <div class="security-status-row">

            <span>
                Account
            </span>

            <strong class="status-success">
                ACTIVE
            </strong>

        </div>


        <div class="security-status-row">

            <span>
                Role
            </span>

            <strong>
                {{ $admin->roles->first()?->name ?? 'Administrator' }}
            </strong>

        </div>


        <div class="security-status-row">

            <span>
                Login IP
            </span>

            <strong>
                {{ $admin->last_login_ip ?? '—' }}
            </strong>

        </div>


        <div class="security-status-row">

            <span>
                Last Login
            </span>

            <strong>
                {{ $admin->last_login_at
                    ? $admin->last_login_at->format('d M Y H:i')
                    : 'First Session'
                }}
            </strong>

        </div>

    </section>

</div>


<section class="panel activity-panel">

    <div class="panel-header">

        <div>

            <div class="panel-eyebrow">
                AUDIT TRAIL
            </div>

            <h2>
                Recent Administration Activity
            </h2>

        </div>

        <div class="panel-count">
            Today:
            {{ number_format($stats['audit_events_today']) }}
        </div>

    </div>


    <div class="activity-table-wrap">

        <table class="activity-table">

            <thead>

                <tr>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Actor</th>
                    <th>IP Address</th>
                    <th>Date / Time</th>
                </tr>

            </thead>

            <tbody>

                @forelse($recentActivities as $activity)

                    <tr>

                        <td>

                            <span class="activity-action">
                                {{ $activity->action }}
                            </span>

                        </td>

                        <td>
                            {{ $activity->module ?? '—' }}
                        </td>

                        <td>
                            {{ $activity->actor_type }}

                            @if($activity->actor_id)
                                #{{ $activity->actor_id }}
                            @endif
                        </td>

                        <td>
                            {{ $activity->ip_address ?? '—' }}
                        </td>

                        <td>
                            {{ optional($activity->created_at)
                                ->format('d M Y H:i:s') }}
                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="empty-table"
                        >
                            No administration activity yet.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>

@endsection