@extends('admin.layouts.app')

@section('title', 'TradeX Admin Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/dashboard-command-center.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-eyebrow">TRADEX ADMINISTRATION</div>
        <h1>Welcome back, <span>{{ $admin->name }}</span></h1>
        <p>Monitor the TradeX platform from one secure workspace.</p>
    </div>

    <div class="dashboard-brand-side">
        <div class="dashboard-brand-lockup" aria-label="TradeX — Learn. Trade. Grow.">
            <img
                src="{{ asset('admin/brand/tradex-logo.webp') }}"
                alt="TradeX — Learn. Trade. Grow."
                width="310"
                height="320"
            >
        </div>

        <div class="page-date">{{ $systemDateDisplay }}</div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue">◎</div>
        <div class="stat-content">
            <span>Admin Users</span>
            <strong>{{ number_format($stats['admins']) }}</strong>
            <small>Registered administrators</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">✓</div>
        <div class="stat-content">
            <span>Active Admins</span>
            <strong>{{ number_format($stats['active_admins']) }}</strong>
            <small>Enabled accounts</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon gold">♛</div>
        <div class="stat-content">
            <span>Admin Roles</span>
            <strong>{{ number_format($stats['roles']) }}</strong>
            <small>RBAC roles configured</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">⚿</div>
        <div class="stat-content">
            <span>Permissions</span>
            <strong>{{ number_format($stats['permissions']) }}</strong>
            <small>Security permissions</small>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <section class="panel quick-panel">
        <div class="panel-header">
            <div>
                <div class="panel-eyebrow">QUICK ACCESS</div>
                <h2>Platform Management</h2>
            </div>
        </div>

        <div class="quick-grid">
            @if($admin->hasPermission('users.view'))
                <a class="quick-card quick-card-link" href="{{ route('admin.customers.dashboard') }}" aria-label="Open Customer Management">
                    <div class="quick-icon">◎</div>
                    <div>
                        <strong>Customers</strong>
                        <span>Customer management & overview</span>
                    </div>
                    <div class="soon-tag">ADMIN-20</div>
                </a>
            @endif

            @if($admin->hasPermission('education.view'))
                <a class="quick-card quick-card-link" href="{{ route('admin.education.courses.index') }}" aria-label="Open Education Management">
                    <div class="quick-icon">▣</div>
                    <div>
                        <strong>Education</strong>
                        <span>Courses, modules & lessons</span>
                    </div>
                    <div class="soon-tag">ADMIN-03</div>
                </a>
            @endif

            @if($admin->hasPermission('trade_ideas.view'))
                <a class="quick-card quick-card-link" href="{{ route('admin.trade_ideas.index') }}" aria-label="Open Long-Term Trade Ideas">
                    <div class="quick-icon">◇</div>
                    <div>
                        <strong>Long-Term Trade Ideas</strong>
                        <span>Forex, Crypto, Deriv & Stocks</span>
                    </div>
                    <div class="soon-tag">LONG TERM</div>
                </a>

                <a class="quick-card quick-card-link" href="{{ route('admin.daily_trade_ideas.index') }}" aria-label="Open Daily Trade Ideas">
                    <div class="quick-icon">◈</div>
                    <div>
                        <strong>Daily Trade Ideas</strong>
                        <span>Daily trading idea operations</span>
                    </div>
                    <div class="soon-tag">DAILY</div>
                </a>
            @endif

            @if($admin->hasPermission('payments.view'))
                <a class="quick-card quick-card-link" href="{{ route('admin.payments.index') }}" aria-label="Open Payments">
                    <div class="quick-icon">$</div>
                    <div>
                        <strong>Payments</strong>
                        <span>PayPal, Stripe, crypto & bank</span>
                    </div>
                    <div class="soon-tag">ADMIN-08</div>
                </a>
            @endif

            @if($admin->hasPermission('payouts.view'))
                <a class="quick-card quick-card-link" href="{{ route('admin.payouts.index') }}" aria-label="Open Payouts">
                    <div class="quick-icon">⇅</div>
                    <div>
                        <strong>Payouts</strong>
                        <span>Affiliate payout operations</span>
                    </div>
                    <div class="soon-tag">PAYOUTS</div>
                </a>
            @endif

            @if($admin->hasPermission('our_channel.view'))
                <a class="quick-card quick-card-link" href="{{ route('admin.our_channel.index') }}" aria-label="Open Our Channel">
                    <div class="quick-icon">▶</div>
                    <div>
                        <strong>Our Channel</strong>
                        <span>Manage free channel content</span>
                    </div>
                    <div class="soon-tag">CHANNEL</div>
                </a>
            @endif
        </div>
    </section>

    <section class="panel security-panel">
        <div class="panel-header">
            <div>
                <div class="panel-eyebrow">SECURITY</div>
                <h2>Administration Status</h2>
            </div>
        </div>

        <div class="security-status-row">
            <span>Admin Guard</span>
            <strong class="status-success">ACTIVE</strong>
        </div>

        <div class="security-status-row">
            <span>Account</span>
            <strong class="status-success">ACTIVE</strong>
        </div>

        <div class="security-status-row">
            <span>Role</span>
            <strong>{{ $admin->roles->first()?->name ?? 'Administrator' }}</strong>
        </div>

        <div class="security-status-row">
            <span>Login IP</span>
            <strong>{{ $admin->last_login_ip ?? '—' }}</strong>
        </div>

        <div class="security-status-row">
            <span>Last Login</span>
            <strong title="{{ $systemTimezone }}">{{ $lastLoginDisplay }}</strong>
        </div>

        <div class="security-status-row">
            <span>System Time</span>
            <strong
                class="dashboard-live-clock"
                data-system-clock
                data-start="{{ $systemTimeIso }}"
                data-timezone="{{ $systemTimezone }}"
            >{{ $systemTimeDisplay }}</strong>
            <span class="dashboard-clock-zone">{{ $systemTimezone }}</span>
        </div>
    </section>
</div>

<section class="panel activity-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">RECENT ACTIVITY</div>
            <h2>Platform Activity & Latest Records</h2>
        </div>

        <div class="panel-count">
            @if($activityRecords)
                {{ number_format($activityRecords->total()) }} records
                @if($activityType === 'audit')
                    · Today {{ number_format($stats['audit_events_today']) }}
                @endif
            @endif
        </div>
    </div>

    @if($activityTabs !== [])
        <div class="dashboard-activity-toolbar">
            <nav class="dashboard-activity-tabs" aria-label="Dashboard recent activity">
                @foreach($activityTabs as $type => $label)
                    <a
                        href="{{ route('admin.dashboard', ['activity' => $type, 'per_page' => $perPage]) }}"
                        class="dashboard-activity-tab {{ $activityType === $type ? 'active' : '' }}"
                        @if($activityType === $type) aria-current="page" @endif
                    >{{ $label }}</a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('admin.dashboard') }}" class="dashboard-per-page-form">
                <input type="hidden" name="activity" value="{{ $activityType }}">
                <label for="dashboardPerPage">Per View</label>
                <select id="dashboardPerPage" name="per_page">
                    @foreach([5, 10, 20, 50] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <button type="submit" class="secondary-btn">Apply</button>
            </form>
        </div>

        @if($activityType === 'customers')
            <div class="activity-table-wrap">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Account</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activityRecords as $customer)
                            <tr>
                                <td>
                                    <span class="activity-action">{{ $customer->name }}</span>
                                    <span class="activity-secondary">{{ $customer->country_code ?? '—' }}</span>
                                </td>
                                <td>
                                    {{ $customer->email }}
                                    <span class="activity-secondary">{{ $customer->whatsapp_number ?? '—' }}</span>
                                </td>
                                <td>{{ $customer->account_type ?? '—' }}</td>
                                <td><span class="activity-status">{{ $customer->status ?? '—' }}</span></td>
                                <td title="{{ $systemTimezone }}">{{ $activityTimestampDisplays[(string) $customer->getKey()] ?? '—' }}</td>
                                <td>
                                    <a class="secondary-btn activity-view-link" href="{{ route('admin.users.show', $customer) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-table">No customers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($activityType === 'payments')
            <div class="activity-table-wrap">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Provider</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Cleared</th>
                            <th>Order</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activityRecords as $payment)
                            @php($amount = $money->present((int) $payment->gross_amount_minor, (string) $payment->currency))
                            @php($providerLabel = match ($payment->provider) {
                                'USDT_TRC20' => 'Binance / USDT TRC20',
                                'LOCAL_BANK' => 'Local Bank',
                                'NOWPAYMENTS' => 'NOWPayments',
                                default => $payment->provider,
                            })
                            <tr>
                                <td>
                                    <span class="activity-action">{{ $payment->user?->name ?? '—' }}</span>
                                    <span class="activity-secondary">{{ $payment->user?->email ?? '—' }}</span>
                                </td>
                                <td>{{ $providerLabel }}</td>
                                <td>{{ $amount['currency'] }} {{ $amount['amount'] }}</td>
                                <td><span class="activity-status">{{ $payment->status }}</span></td>
                                <td title="{{ $systemTimezone }}">{{ $activityTimestampDisplays[(string) $payment->getKey()] ?? '—' }}</td>
                                <td>{{ $payment->order?->uuid ?? '—' }}</td>
                                <td>
                                    @if($payment->order)
                                        <a class="secondary-btn activity-view-link" href="{{ route('admin.payments.show', $payment->order) }}">View</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-table">No payments found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($activityType === 'audit')
            <div class="activity-table-wrap">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Actor</th>
                            <th>IP Address</th>
                            <th>Date / Time</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activityRecords as $activity)
                            <tr>
                                <td><span class="activity-action">{{ $activity->action }}</span></td>
                                <td>{{ $activity->module ?? '—' }}</td>
                                <td>
                                    {{ $activity->actor_type }}
                                    @if($activity->actor_id)
                                        #{{ $activity->actor_id }}
                                    @endif
                                </td>
                                <td>{{ $activity->ip_address ?? '—' }}</td>
                                <td title="{{ $systemTimezone }}">{{ $activityTimestampDisplays[(string) $activity->getKey()] ?? '—' }}</td>
                                <td>
                                    <a class="secondary-btn activity-view-link" href="{{ route('admin.audit.show', $activity) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-table">No administration activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if($activityRecords)
            <div class="dashboard-activity-pagination">
                @include('admin.components.pagination', ['paginator' => $activityRecords])
            </div>
        @endif
    @else
        <div class="dashboard-empty-access">
            No recent-activity modules are available for this administrator role.
        </div>
    @endif
</section>

@endsection

@push('scripts')
<script src="{{ asset('admin/js/dashboard-command-center.js') }}"></script>
@endpush
