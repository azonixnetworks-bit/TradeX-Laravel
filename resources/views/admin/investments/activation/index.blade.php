@extends('admin.layouts.app')

@section('title', 'Investment Activation Queue')
@section('page-title', 'Investment Activation Queue')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ACTIVATION GATE</div>
        <h1>Activation <span>Queue</span></h1>
        <p>ACTIVE is allowed only after deposit verification, current risk acceptance, verified signed agreement and verified customer TRC20 payout wallet.</p>
    </div>
</div>

<section class="panel users-panel">
    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Investment</th>
                <th>Customer</th>
                <th>Deposit</th>
                <th>Risk</th>
                <th>Agreement</th>
                <th>Wallet</th>
                <th>Ready</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $investment = $row['investment'];
                    $ready = $row['readiness'];
                @endphp
                <tr>
                    <td>{{ $investment->investment_code }}</td>
                    <td>{{ $investment->user?->name ?? '—' }}</td>
                    <td>{{ $ready['deposit_verified'] ? '✓' : '—' }}</td>
                    <td>{{ $ready['risk_accepted'] ? '✓' : '—' }}</td>
                    <td>{{ $ready['agreement_verified'] ? '✓' : '—' }}</td>
                    <td>{{ $ready['wallet_verified'] ? '✓' : '—' }}</td>
                    <td><strong>{{ $ready['ready'] ? 'READY' : 'BLOCKED' }}</strong></td>
                    <td>
                        <a class="secondary-btn" href="{{ route('admin.investments.show', $investment) }}">Review</a>
                        @if($canActivate && $ready['ready'])
                            <form method="POST" action="{{ route('admin.investments.activate', $investment) }}" style="display:inline;">
                                @csrf
                                <button class="primary-btn" type="submit">Activate</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No investments are waiting for activation.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
