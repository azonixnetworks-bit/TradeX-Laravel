@extends('admin.layouts.app')

@section('title', 'Trading Floor Management | TradeX Administration')
@section('page-title', 'Trading Floor Management')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · TRADING FLOOR & CLIENT MANAGEMENT</div>
        <h1>Trading Floor <span>Control</span></h1>
        <p>Customer registration, access control, attendance and commission collection in one operational workspace.</p>
    </div>
</div>

<div class="dashboard-grid" style="margin-top: 22px;">
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
        <div class="stat-card__value">{{ number_format((float) $stats['commission_due'], 2) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Overdue Clients</div>
        <div class="stat-card__value">{{ number_format($stats['overdue_clients']) }}</div>
    </div>
</div>

<div class="panel" style="margin-top: 24px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">OPERATIONS FOUNDATION</div>
            <h2>Trading Floor Management</h2>
        </div>
        <span>{{ $generatedAt->utc()->format('Y-m-d H:i:s') }} UTC</span>
    </div>

    <div class="panel-body">
        <p>ADMIN-30 foundation is active. Customer registration, NFC/fingerprint device references, access logs, commission invoices and payment collection are being added as permission-gated workflows.</p>
    </div>
</div>
@endsection
