@extends('admin.layouts.app')

@section('title', 'Security Settings')
@section('page-title', 'Security Settings')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">READ-ONLY SECURITY POSTURE</div>
        <h1>Security <span>Settings</span></h1>
        <p>
            Effective safe runtime indicators only. No security key, secret or emergency control is editable here.
        </p>
    </div>
</div>

@include('admin.settings.partials.nav')

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Environment</span>
        <strong>{{ strtoupper($snapshot['environment']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Debug</span>
        <strong>{{ $snapshot['debug_enabled'] ? 'ENABLED' : 'DISABLED' }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Session Driver</span>
        <strong>{{ strtoupper($snapshot['session_driver']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>HttpOnly</span>
        <strong>{{ $snapshot['session_http_only'] ? 'YES' : 'NO' }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Secure Cookie</span>
        <strong>{{ $snapshot['session_secure'] ? 'YES' : 'NO / ENV CONTROLLED' }}</strong>
    </div>

    <div class="user-stat-card">
        <span>SameSite</span>
        <strong>{{ strtoupper($snapshot['session_same_site']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Content Protection Profile</span>
        <strong>{{ $snapshot['content_protection_profile_present'] ? 'PRESENT' : 'NOT CENTRALIZED' }}</strong>
    </div>
</div>

@if(auth('admin')->user()->hasPermission('security.view'))
    <a
        class="primary-btn"
        href="{{ route('admin.security.content-protection') }}"
    >
        Open Security Posture
    </a>
@endif

<section class="panel" style="padding:18px;margin-top:18px;">
    APP_KEY, hashing/HMAC secrets, session encryption secrets, device keys, KYC keys and infrastructure controls are intentionally unavailable from this page.
</section>
@endsection
