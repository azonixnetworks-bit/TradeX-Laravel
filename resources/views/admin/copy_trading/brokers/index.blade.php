@extends('admin.layouts.app')

@section('title', 'Copy Trading Broker Management')
@section('page-title', 'Broker Management')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · BROKER CONTROL PLANE</div>
        <h1>Broker <span>Management</span></h1>
        <p>
            Configure approved brokers, market categories and TradeX partner
            referral URLs without hard-coding partner links in the app.
        </p>
    </div>

    @if($canManage)
        <a
            class="primary-btn"
            href="{{ route('admin.copy_trading.brokers.create') }}"
        >
            + Add Broker
        </a>
    @endif
</div>

<div class="admin-form-callout">
    <div class="admin-form-callout-icon" aria-hidden="true">◇</div>
    <div>
        <strong>Partner-link boundary</strong>
        Referral URLs are configuration, not source code. Deactivate a broker or
        market immediately when partner onboarding should stop. Existing broker
        account history is preserved.
    </div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">SUPPORTED BROKERS</div>
            <h2>Broker Register</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Broker</th>
                    <th>Markets</th>
                    <th>Partner Link</th>
                    <th>Accounts</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($brokers as $broker)
                <tr>
                    <td>
                        <strong>{{ $broker->name }}</strong><br>
                        <small>{{ $broker->code }}</small>
                    </td>
                    <td>
                        @forelse($broker->markets as $market)
                            <div style="margin-bottom:5px;">
                                {{ $market->market_category }} · {{ $market->required_currency }}
                                · {{ $market->is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </div>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>
                        @foreach($broker->markets as $market)
                            <div style="margin-bottom:5px;">
                                {{ parse_url($market->referral_url, PHP_URL_HOST) ?: 'Configured' }}
                            </div>
                        @endforeach
                    </td>
                    <td>{{ number_format($broker->accounts_count) }}</td>
                    <td>
                        <span class="user-status {{ $broker->is_active ? 'status-active' : 'status-blocked' }}">
                            <span class="user-status-dot"></span>
                            {{ $broker->is_active ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </td>
                    <td>
                        @if($canManage)
                            <a
                                class="secondary-btn"
                                href="{{ route('admin.copy_trading.brokers.edit', ['copyTradeBroker' => $broker]) }}"
                            >
                                Configure
                            </a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:30px;">
                        No Copy Trading brokers configured yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:16px 20px;">
        {{ $brokers->links() }}
    </div>
</section>
@endsection
