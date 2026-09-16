@extends('admin.layouts.app')

@section('title', 'Trading Floor Management | TradeX Administration')
@section('page-title', 'Trading Floor Management')

@section('content')
@php
    $admin = auth('admin')->user();
    $canManageClients = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.clients.manage');
    $canManageCommissions = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.commissions.manage');
    $commissionDueText = collect($stats['commission_due_by_currency'])
        ->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))
        ->implode(' · ');
    $receivedText = collect($stats['received_this_month_by_currency'])
        ->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))
        ->implode(' · ');
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · TRADING FLOOR & CLIENT MANAGEMENT</div>
        <h1>Trading Floor <span>Control</span></h1>
        <p>Customer registration, access control, attendance and commission collection in one operational workspace.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.index') }}">Commission Ledger</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.index') }}">Customer Directory</a>
        @if($canManageClients)
            <a class="btn btn-primary" href="{{ route('admin.trading_floor.clients.create') }}">＋ Register Customer</a>
        @endif
    </div>
</div>

<div class="dashboard-grid" style="margin-top:22px;">
    <div class="stat-card">
        <div class="stat-card__label">Active Clients</div>
        <div class="stat-card__value">{{ number_format($stats['active_clients']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Present Today</div>
        <div class="stat-card__value">{{ number_format($stats['present_today']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Commission Due</div>
        <div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $commissionDueText !== '' ? $commissionDueText : '0.00' }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Received This Month</div>
        <div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $receivedText !== '' ? $receivedText : '0.00' }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Overdue Clients</div>
        <div class="stat-card__value">{{ number_format($stats['overdue_clients']) }}</div>
    </div>
</div>

<div class="panel" style="margin-top:24px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">OPERATIONS</div>
            <h2>Trading Floor Management</h2>
        </div>
        <span>{{ $generatedAt->utc()->format('Y-m-d H:i:s') }} UTC</span>
    </div>

    <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;">
            <a href="{{ route('admin.trading_floor.clients.index') }}" class="panel" style="padding:18px;text-decoration:none;">
                <div class="panel-eyebrow">CUSTOMERS</div>
                <h3 style="margin:6px 0;">Customer Directory</h3>
                <p style="margin:0;opacity:.7;">Profiles, floor/desk allocation, commission setup and access status.</p>
            </a>
            @if($canManageClients)
                <a href="{{ route('admin.trading_floor.clients.create') }}" class="panel" style="padding:18px;text-decoration:none;">
                    <div class="panel-eyebrow">REGISTRATION</div>
                    <h3 style="margin:6px 0;">Register Customer</h3>
                    <p style="margin:0;opacity:.7;">Create a trading-floor client and configure the commercial agreement.</p>
                </a>
            @endif
            <a href="{{ route('admin.trading_floor.commissions.index') }}" class="panel" style="padding:18px;text-decoration:none;">
                <div class="panel-eyebrow">COMMISSION</div>
                <h3 style="margin:6px 0;">Billing & Collection</h3>
                <p style="margin:0;opacity:.7;">Invoices, partial payments, balances, overdue items and payment proof.</p>
            </a>
            <div class="panel" style="padding:18px;">
                <div class="panel-eyebrow">ACCESS CONTROL</div>
                <h3 style="margin:6px 0;">NFC & Fingerprint</h3>
                <p style="margin:0;opacity:.7;">Assign protected NFC/card or biometric device references from each customer profile.</p>
            </div>
        </div>
    </div>
</div>
@endsection
