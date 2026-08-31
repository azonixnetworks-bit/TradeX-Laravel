@extends('admin.layouts.app')

@section('title', 'TradeX Generic Crypto Review')
@section('page-title', 'Generic Crypto Deposits')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">MANUAL CRYPTO RECONCILIATION</div>
        <h1>Generic Crypto <span>Review</span></h1>
        <p>Review BTC, USDC, ETH, BNB and other admin-configured crypto payments. Customer evidence never activates premium before authorized verification.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.payments.index') }}">All Payments</a>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">
        {{ session('success') }}
    </section>
@endif

@if($errors->any())
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">
        <strong>Review not completed:</strong> {{ $errors->first() }}
    </section>
@endif

<section class="panel user-filter-panel">
    <form method="GET" class="user-filter-form">
        <div class="filter-search">
            <label>Search</label>
            <input name="search" value="{{ request('search') }}" placeholder="TX hash, coin, network, order UUID, user...">
        </div>
        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach(['SUBMITTED','APPROVED','REJECTED'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button class="primary-btn">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.payments.crypto.index') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">GENERIC CRYPTO</div>
            <h2>Review Queue</h2>
        </div>
        <div class="panel-count">{{ number_format($deposits->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Deposit</th>
                    <th>User</th>
                    <th>Coin</th>
                    <th>Network</th>
                    <th>Amount</th>
                    <th>TX Hash</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($deposits as $deposit)
                <tr>
                    <td>{{ $deposit->uuid }}</td>
                    <td>
                        {{ $deposit->user?->name ?? '—' }}<br>
                        <small>{{ $deposit->user?->email ?? '—' }}</small>
                    </td>
                    <td><strong>{{ $deposit->coin_symbol }}</strong></td>
                    <td>{{ $deposit->network }}</td>
                    <td>{{ $deposit->amount_decimal }}</td>
                    <td>
                        <code>{{ \Illuminate\Support\Str::limit($deposit->tx_hash, 24) }}</code>
                        @if($deposit->proof_path && ($permissions['verify'] || $permissions['reconcile']))
                            <br><a class="secondary-btn" style="margin-top:6px;" href="{{ route('admin.payments.crypto.proof', $deposit) }}">Protected Proof</a>
                        @endif
                    </td>
                    <td><strong>{{ $deposit->status }}</strong></td>
                    <td>
                        @if($deposit->status === 'SUBMITTED')
                            @if($permissions['verify'])
                                <form method="POST" action="{{ route('admin.payments.crypto.approve', $deposit) }}" style="margin-bottom:8px;">
                                    @csrf
                                    @method('PATCH')
                                    <textarea name="reason" required minlength="10" maxlength="500" rows="2" placeholder="Verification evidence note"></textarea>
                                    <button class="primary-btn" type="submit" style="margin-top:6px;">Approve</button>
                                </form>
                            @endif

                            @if($permissions['reject'])
                                <form method="POST" action="{{ route('admin.payments.crypto.reject', $deposit) }}">
                                    @csrf
                                    @method('PATCH')
                                    <textarea name="reason" required minlength="10" maxlength="500" rows="2" placeholder="Rejection reason"></textarea>
                                    <button class="secondary-btn" type="submit" style="margin-top:6px;">Reject</button>
                                </form>
                            @endif
                        @else
                            {{ $deposit->reviewedByAdmin?->name ?? 'Reviewed' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No generic crypto deposits match the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">
        @include('admin.components.pagination', ['paginator' => $deposits])
    </div>
</section>
@endsection
