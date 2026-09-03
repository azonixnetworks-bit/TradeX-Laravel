@extends('admin.layouts.app')

@section('title', 'Investment Deposit Verification')
@section('page-title', 'Investment Deposit Verification')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">USDT TRC20 CONTROL</div>
        <h1>Deposit <span>Verification</span></h1>
        <p>Investment V1 accepts USDT on TRON/TRC20 only. Deposits are immutable ledger entries and duplicate network + TxID values are blocked.</p>
    </div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">PENDING VERIFICATION</div>
            <h2>{{ number_format($pendingDeposits->total()) }} pending deposits</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Investment</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Network</th>
                <th>TxID</th>
                <th>Occurred</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($pendingDeposits as $deposit)
                <tr>
                    <td>
                        <a href="{{ route('admin.investments.show', $deposit->investment) }}">
                            {{ $deposit->investment?->investment_code }}
                        </a>
                    </td>
                    <td>{{ $deposit->investment?->user?->name ?? '—' }}</td>
                    <td>{{ $deposit->asset_symbol }} {{ $deposit->amount }}</td>
                    <td>{{ $deposit->network }}</td>
                    <td><code>{{ $deposit->txid }}</code></td>
                    <td>{{ $deposit->occurred_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        @if($canVerify)
                            <form method="POST" action="{{ route('admin.investments.deposits.verify', [$deposit->investment, $deposit]) }}">
                                @csrf
                                <button class="primary-btn" type="submit">Verify</button>
                            </form>
                        @else
                            Review only
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No pending investment deposits.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:18px;">
        {{ $pendingDeposits->links() }}
    </div>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">AWAITING DEPOSIT</div>
            <h2>Investments waiting for USDT</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Investment</th>
                <th>Customer</th>
                <th>Expected</th>
                <th>Product</th>
            </tr>
            </thead>
            <tbody>
            @forelse($awaitingInvestments as $investment)
                <tr>
                    <td>
                        <a href="{{ route('admin.investments.show', $investment) }}">
                            {{ $investment->investment_code }}
                        </a>
                    </td>
                    <td>{{ $investment->user?->name ?? '—' }}</td>
                    <td>USDT {{ number_format((float) $investment->deposit_amount, 2) }}</td>
                    <td>{{ $investment->product?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No investments are waiting for deposit.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
