@extends('admin.layouts.app')

@section('title', 'Investment Profit Engine')
@section('page-title', 'Investment Profit Engine')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-24 • PHASE 3</div>
        <h1>Profit Review & Distribution</h1>
        <p>Realized P&amp;L only • HWM/loss carry-forward • 80/20 • maker-checker approval • USDT TRC20 payout.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.investments.dashboard') }}">Investment Dashboard</a>
</div>

@if($errors->any())
    <section class="panel" style="padding:18px;margin-bottom:18px;">
        <strong>Action could not be completed.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </section>
@endif

@if(session('success'))
    <section class="panel" style="padding:18px;margin-bottom:18px;"><strong>{{ session('success') }}</strong></section>
@endif

<section class="panel users-panel">
    <div class="panel-header">
        <div><div class="panel-eyebrow">REVIEW QUEUE</div><h2>Profit Periods</h2></div>
        <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
            <div class="filter-item"><label>Status</label><input name="status" value="{{ request('status') }}" placeholder="APPROVAL_PENDING"></div>
            <label><input type="checkbox" name="due" value="1" {{ request()->boolean('due') ? 'checked' : '' }}> Due only</label>
            <button class="secondary-btn" type="submit">Filter</button>
        </form>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Investment</th><th>Customer</th><th>Period</th><th>Review End</th><th>Status</th><th>Outcome</th><th>Eligible Profit</th><th>Customer 80%</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($periods as $period)
                <tr>
                    <td>{{ $period->investment?->investment_code }}</td>
                    <td>{{ $period->investment?->user?->name ?? '—' }}</td>
                    <td>#{{ $period->sequence }}<br><small>{{ $period->period_start?->format('Y-m-d') }} → {{ $period->period_end?->format('Y-m-d') }}</small></td>
                    <td>{{ $period->period_end?->format('Y-m-d') }} {{ $period->period_end?->isPast() || $period->period_end?->isToday() ? '• DUE' : '' }}</td>
                    <td>{{ $period->status }}</td>
                    <td>{{ $period->distribution_outcome ?? '—' }}</td>
                    <td>{{ $period->eligible_net_profit_usdt }}</td>
                    <td>{{ $period->customer_profit_share_usdt }}</td>
                    <td><a class="secondary-btn" href="{{ route('admin.investments.profit_periods.show', [$period->investment_id, $period]) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="9">No profit periods found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:18px;">{{ $periods->links() }}</div>
</section>
@endsection
