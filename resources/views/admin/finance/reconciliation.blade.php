@extends('admin.layouts.app')

@section('title', 'Finance Reconciliation')
@section('page-title', 'Finance Reconciliation')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">LEDGER HEALTH</div>
        <h1>Finance <span>Reconciliation</span></h1>
        <p>Read-only consistency checks across payment orders, payment adjustments and payout allocations.</p>
    </div>

    <a class="secondary-btn" href="{{ route('admin.finance.dashboard') }}">Finance Dashboard</a>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Detected Issues</span>
        <strong>{{ number_format($issue_count) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Pending Manual Reviews</span>
        <strong class="text-gold">{{ number_format($pending_manual_reviews) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Active Payouts</span>
        <strong>{{ number_format($active_payouts) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Scan Limit / Check</span>
        <strong>{{ number_format($scan_limit) }}</strong>
    </div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">RECONCILIATION FINDINGS</div>
            <h2>Ledger Consistency</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Record</th>
                    <th>Code</th>
                    <th>Finding</th>
                </tr>
            </thead>
            <tbody>
            @forelse($issues as $issue)
                <tr>
                    <td>{{ $issue['domain'] }}</td>
                    <td>{{ $issue['uuid'] ?: '#'.$issue['id'] }}</td>
                    <td><strong>{{ $issue['code'] }}</strong></td>
                    <td>{{ $issue['message'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No reconciliation issues detected in the configured scan window.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="padding:22px;margin-top:18px;">
    <div class="panel-eyebrow">BOUNDARY</div>
    <p>This page does not mutate payment, affiliate commission or payout ledgers. Fixes must go through their owning audited module/workflow.</p>
</section>
@endsection
