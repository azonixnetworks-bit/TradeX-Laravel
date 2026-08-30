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
        account history is preserved. Permanent delete is available only for a
        broker that has never been used for onboarding or a broker account.
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
                @php
                    $hasAccountHistory = (int) $broker->accounts_count > 0;
                    $hasOnboardingHistory = (bool) $broker->onboarding_intents_exists;
                    $canDeleteBroker = ! $hasAccountHistory && ! $hasOnboardingHistory;
                @endphp
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
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <a
                                    class="secondary-btn"
                                    href="{{ route('admin.copy_trading.brokers.edit', ['copyTradeBroker' => $broker]) }}"
                                >
                                    Configure
                                </a>

                                @if($canDeleteBroker)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.copy_trading.brokers.destroy', ['copyTradeBroker' => $broker]) }}"
                                        onsubmit="return confirm('Delete this unused broker permanently? This action is only allowed when no onboarding or broker-account history exists.');"
                                        style="margin:0;"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="secondary-btn"
                                            style="border-color:rgba(255,105,120,.65);color:#ff6978;"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @else
                                    <button
                                        type="button"
                                        class="secondary-btn"
                                        disabled
                                        title="History exists. Deactivate this broker instead of deleting it."
                                        style="opacity:.55;cursor:not-allowed;"
                                    >
                                        Delete locked
                                    </button>
                                @endif
                            </div>
                            @unless($canDeleteBroker)
                                <small style="display:block;margin-top:6px;opacity:.72;">
                                    History preserved · deactivate instead
                                </small>
                            @endunless
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
