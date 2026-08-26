@extends('admin.layouts.app')

@section('title', 'Affiliate Settings')
@section('page-title', 'Affiliate Settings')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">OWNERSHIP STATUS</div>
        <h1>Affiliate <span>Settings</span></h1>
        <p>Read-only ownership view for the frozen affiliate commercial configuration.</p>
    </div>
</div>

@include('admin.settings.partials.nav')

<section class="panel" style="padding:22px;">
    <h2>{{ $snapshot['owner'] }}</h2>
    <p>{{ $snapshot['commercial_policy'] }}</p>

    <div class="user-stat-grid" style="margin-top:18px;">
        <div class="user-stat-card">
            <span>API Default Page Size</span>
            <strong>{{ $snapshot['api_pagination_default'] }}</strong>
        </div>

        <div class="user-stat-card">
            <span>API Maximum Page Size</span>
            <strong>{{ $snapshot['api_pagination_max'] }}</strong>
        </div>
    </div>

    @if(auth('admin')->user()->hasPermission('affiliates.view'))
        <a
            class="primary-btn"
            href="{{ route('admin.affiliates.index') }}"
        >
            Open Affiliate Management
        </a>
    @endif
</section>

<section class="panel" style="padding:18px;margin-top:18px;">
    No affiliate stage, commission percentage, milestone gift or special commission mutation is available from System Settings.
</section>
@endsection
