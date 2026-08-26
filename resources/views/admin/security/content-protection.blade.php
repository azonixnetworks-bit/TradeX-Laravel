@extends('admin.layouts.app')

@section('title', 'Protection Posture')
@section('page-title', 'Protection Posture')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SECURITY POSTURE</div>
        <h1>Protection <span>Health</span></h1>
        <p>
            Read-only effective runtime checks. This page does not expose or edit secrets and does not claim to replace infrastructure security monitoring.
        </p>
    </div>
</div>

@include('admin.security.partials.stats')

<div
    style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;"
>
    @if($permissions['events'])
        <a
            class="secondary-btn"
            href="{{ route('admin.security.events') }}"
        >
            Security Events
        </a>
    @endif

    <a
        class="secondary-btn"
        href="{{ route('admin.security.sessions') }}"
    >
        Sessions & Devices
    </a>

    <a
        class="primary-btn"
        href="{{ route('admin.security.content-protection') }}"
    >
        Protection Posture
    </a>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Passing Checks</span>
        <strong>{{ $summary['pass'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Warnings</span>
        <strong>{{ $summary['warn'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Information</span>
        <strong>{{ $summary['info'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Runtime</span>
        <strong>{{ strtoupper($runtime['environment']) }}</strong>
        <small>{{ $runtime['session_driver'] }} sessions</small>
    </div>
</div>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Important:</strong>
    a PASS means the Laravel runtime configuration inspected by this module is aligned with the named control. It does not certify CDN, reverse proxy, operating-system, database, cloud IAM or network configuration.
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">EFFECTIVE CONFIGURATION</div>
            <h2>Security Checks</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Control</th>
                    <th>Status</th>
                    <th>Observation</th>
                </tr>
            </thead>

            <tbody>
            @foreach($checks as $check)
                <tr>
                    <td>
                        <strong>{{ $check['name'] }}</strong>
                    </td>

                    <td>
                        <strong>{{ $check['status'] }}</strong>
                    </td>

                    <td>
                        {{ $check['detail'] }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

<section
    class="panel"
    style="padding:18px;margin-top:18px;"
>
    <div class="panel-eyebrow">READ-ONLY BY DESIGN</div>
    <p>
        `security.manage` remains reserved for a future explicitly designed emergency/security-settings workflow. ADMIN-11 does not write runtime configuration from the web UI.
    </p>
</section>
@endsection
