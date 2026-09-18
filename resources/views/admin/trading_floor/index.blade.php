@extends('admin.layouts.app')

@section('title', 'Trading Floor Management | TradeX Administration')
@section('page-title', 'Trading Floor Management')

@section('content')
@php
    $admin = auth('admin')->user();
    $canManageClients = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.clients.manage');
    $canManageAccessSettings = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.settings.manage');
    $canViewReports = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.reports.view');
    $canViewResources = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.resources.view');
    $canViewMemberships = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.memberships.view');
    $commissionDueText = collect($stats['commission_due_by_currency'])->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))->implode(' · ');
    $receivedText = collect($stats['received_this_month_by_currency'])->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))->implode(' · ');
@endphp

<div class="page-header">
    <div><div class="page-eyebrow">ADMIN-30 / ADMIN-31 · TRADING FLOOR MANAGEMENT</div><h1>Trading Floor <span>Control</span></h1><p>Customer, access, billing, resource, desk, package and membership operations in one workspace.</p></div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        @if($canViewResources)<a class="btn btn-secondary" href="{{ route('admin.trading_floor.resources.index') }}">Floor Resources</a>@endif
        @if($canViewMemberships)<a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Memberships</a>@endif
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.access.index') }}">Attendance & Access</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.index') }}">Commission Ledger</a>
        @if($canViewReports)<a class="btn btn-secondary" href="{{ route('admin.trading_floor.reports.index') }}">Reports</a>@endif
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.index') }}">Customer Directory</a>
        @if($canManageClients)<a class="btn btn-primary" href="{{ route('admin.trading_floor.clients.create') }}">＋ Register Customer</a>@endif
    </div>
</div>

<div class="dashboard-grid" style="margin-top:22px;">
    <div class="stat-card"><div class="stat-card__label">Active Clients</div><div class="stat-card__value">{{ number_format($stats['active_clients']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Present Now</div><div class="stat-card__value">{{ number_format($stats['present_today']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Commission Due</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $commissionDueText !== '' ? $commissionDueText : '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Received This Month</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $receivedText !== '' ? $receivedText : '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Overdue Clients</div><div class="stat-card__value">{{ number_format($stats['overdue_clients']) }}</div></div>
</div>

<div class="panel" style="margin-top:24px;">
    <div class="panel-header"><div><div class="panel-eyebrow">OPERATIONS</div><h2>Trading Floor Management</h2></div><span>{{ $generatedAt->utc()->format('Y-m-d H:i:s') }} UTC</span></div>
    <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;">
            @if($canViewResources)<a href="{{ route('admin.trading_floor.resources.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">ADMIN-31</div><h3 style="margin:6px 0;">Floors, Desks & Packages</h3><p style="margin:0;opacity:.7;">Physical floors, desk availability, pricing and package configuration.</p></a>@endif
            @if($canViewMemberships)<a href="{{ route('admin.trading_floor.memberships.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">MEMBERSHIP</div><h3 style="margin:6px 0;">Membership Management</h3><p style="margin:0;opacity:.7;">Membership periods, package snapshots, seat allocation and payment status.</p></a>@endif
            <a href="{{ route('admin.trading_floor.clients.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">CUSTOMERS</div><h3 style="margin:6px 0;">Customer Directory</h3><p style="margin:0;opacity:.7;">Profiles, floor/desk allocation, commission setup and access status.</p></a>
            @if($canManageClients)<a href="{{ route('admin.trading_floor.clients.create') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">REGISTRATION</div><h3 style="margin:6px 0;">Register Customer</h3><p style="margin:0;opacity:.7;">Create a trading-floor client and configure the commercial agreement.</p></a>@endif
            <a href="{{ route('admin.trading_floor.commissions.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">COMMISSION</div><h3 style="margin:6px 0;">Billing & Collection</h3><p style="margin:0;opacity:.7;">Invoices, partial payments, balances, overdue items and payment proof.</p></a>
            <a href="{{ route('admin.trading_floor.access.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">ATTENDANCE</div><h3 style="margin:6px 0;">Access Monitor</h3><p style="margin:0;opacity:.7;">Entry/exit history, warnings, denied scans and current presence.</p></a>
            @if($canViewReports)
                <a href="{{ route('admin.trading_floor.reports.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">REPORTING</div><h3 style="margin:6px 0;">Reports & Statements</h3><p style="margin:0;opacity:.7;">Attendance, finance exports, live presence and printable customer statements.</p></a>
                <a href="{{ route('admin.trading_floor.reminders.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">REMINDERS</div><h3 style="margin:6px 0;">Reminder Queue</h3><p style="margin:0;opacity:.7;">Due, overdue and payment confirmation reminders ready for provider delivery.</p></a>
            @endif
            <a href="{{ route('admin.trading_floor.clients.index') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">CREDENTIALS</div><h3 style="margin:6px 0;">NFC & Fingerprint</h3><p style="margin:0;opacity:.7;">Assign protected card or biometric device references from customer profiles.</p></a>
            @if($canManageAccessSettings)<a href="{{ route('admin.trading_floor.access.settings') }}" class="panel" style="padding:18px;text-decoration:none;"><div class="panel-eyebrow">ENFORCEMENT</div><h3 style="margin:6px 0;">Reader & Access Settings</h3><p style="margin:0;opacity:.7;">Provision devices and configure overdue commission access rules.</p></a>@endif
        </div>
    </div>
</div>
@endsection
