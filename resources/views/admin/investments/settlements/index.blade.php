@extends('admin.layouts.app')

@section('title', 'Investment Settlements')
@section('page-title', 'Investment Settlements')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-24 · PHASE 4</div>
        <h1>Terminal Settlements</h1>
        <p>Maturity and early-termination settlement control for attributable spot-crypto assets.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.investments.dashboard') }}">Investment Dashboard</a>
</div>

@if($errors->any())
    <section class="panel" style="padding:18px;margin-bottom:18px;">
        <strong>Action could not be completed.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </section>
@endif

@if($maturityDue->isNotEmpty())
<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">MATURITY QUEUE</div><h2>Investments Due for Maturity Settlement</h2></div>
    </div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Investment</th><th>Customer</th><th>Maturity</th><th>Principal Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @foreach($maturityDue as $investment)
                <tr>
                    <td><a href="{{ route('admin.investments.show', $investment) }}">{{ $investment->investment_code }}</a></td>
                    <td>{{ $investment->user?->name ?? '—' }}</td>
                    <td>{{ $investment->maturity_date?->toDateString() ?? '—' }}</td>
                    <td>{{ $investment->principal_settlement_date?->toDateString() ?? '—' }}</td>
                    <td>{{ $investment->principal_settlement_status }}</td>
                    <td>
                        @if($canPrepare)
                            <form method="POST" action="{{ route('admin.investments.settlements.maturity.prepare', $investment) }}">
                                @csrf
                                <button class="primary-btn" type="submit">Prepare Maturity Settlement</button>
                            </form>
                        @else
                            View only
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

<section class="panel users-panel">
    <div class="panel-header">
        <div><div class="panel-eyebrow">SETTLEMENT LEDGER</div><h2>Terminal Settlement Records</h2></div>
    </div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Investment</th><th>Customer</th><th>Type</th><th>Cutoff</th><th>Due</th><th>Return Rule</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($settlements as $settlement)
                <tr>
                    <td>{{ $settlement->investment?->investment_code ?? '—' }}</td>
                    <td>{{ $settlement->investment?->user?->name ?? '—' }}</td>
                    <td>{{ $settlement->type }}</td>
                    <td>{{ $settlement->cutoff_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $settlement->due_at?->toDateString() ?? '—' }}</td>
                    <td>{{ number_format($settlement->return_bps / 100, 2) }}%</td>
                    <td>{{ $settlement->status }}</td>
                    <td>
                        @if($settlement->investment)
                            <a class="secondary-btn" href="{{ route('admin.investments.settlements.show', [$settlement->investment, $settlement]) }}">Open</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No terminal settlements have been created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">{{ $settlements->links() }}</div>
</section>
@endsection
