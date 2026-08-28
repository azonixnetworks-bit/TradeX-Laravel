@extends('admin.layouts.app')

@section('title', 'TradeX Customers')
@section('page-title', 'Customer Management')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">CUSTOMER MANAGEMENT</div>
        <h1>TradeX <span>Customers</span></h1>
        <p>
            One control surface for registered customers, onboarding,
            account state and customer-linked operations.
        </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a
            class="secondary-btn"
            href="{{ route('admin.users.index') }}"
        >
            All Customers
        </a>

        @if(auth('admin')->user()?->hasPermission('users.invite'))
            <a
                class="primary-btn"
                href="{{ route('admin.customers.create') }}"
            >
                Add Customer
            </a>
        @endif
    </div>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Total Customers</span>
        <strong>{{ number_format($stats['total']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Active</span>
        <strong class="text-green">{{ number_format($stats['active']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Free</span>
        <strong>{{ number_format($stats['free']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Premium</span>
        <strong class="text-gold">{{ number_format($stats['premium']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Pending Accounts</span>
        <strong>{{ number_format($stats['pending_accounts']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Suspended / Blocked</span>
        <strong class="text-orange">{{ number_format($stats['restricted']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Joined in {{ $recentDays }} Days</span>
        <strong>{{ number_format($stats['recent']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Admin Onboarding Pending</span>
        <strong>{{ number_format($stats['onboarding_pending']) }}</strong>
    </div>
</div>

<section class="panel" style="padding:20px;margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CUSTOMER SEGMENTS</div>
            <h2>Quick Access</h2>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.users.index') }}">
            All Customers
        </a>
        <a class="secondary-btn" href="{{ route('admin.users.index', ['segment' => 'PENDING']) }}">
            Pending Accounts
        </a>
        <a class="secondary-btn" href="{{ route('admin.users.index', ['segment' => 'FREE']) }}">
            Free Customers
        </a>
        <a class="secondary-btn" href="{{ route('admin.users.index', ['segment' => 'PREMIUM']) }}">
            Premium Customers
        </a>
        <a class="secondary-btn" href="{{ route('admin.users.index', ['segment' => 'RESTRICTED']) }}">
            Suspended / Blocked
        </a>
        <a class="secondary-btn" href="{{ route('admin.users.index', ['segment' => 'RECENT']) }}">
            Recently Joined
        </a>
        <a class="secondary-btn" href="{{ route('admin.customers.onboarding') }}">
            Pending Onboarding
        </a>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">RECENT CUSTOMERS</div>
            <h2>Newest Registered Accounts</h2>
        </div>
        <div class="panel-count">
            {{ number_format($stats['recent']) }} in {{ $recentDays }} days
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Country</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($recentCustomers as $customer)
                <tr>
                    <td>
                        <div class="user-contact">
                            <strong>{{ $customer->name }}</strong>
                            <span>{{ $customer->email }}</span>
                        </div>
                    </td>
                    <td>{{ $customer->country_code ?? '—' }}</td>
                    <td>{{ $customer->account_type }}</td>
                    <td>{{ $customer->status }}</td>
                    <td>{{ $customer->created_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td>
                        <a
                            class="table-action"
                            href="{{ route('admin.users.show', $customer) }}"
                            title="View customer"
                        >→</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="users-empty">
                        <strong>No registered customers yet</strong>
                        <span>Customer accounts will appear here after normal verified registration.</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ONBOARDING QUEUE</div>
            <h2>Admin-created Invitations</h2>
        </div>
        <div class="panel-count">
            {{ number_format($stats['onboarding_pending']) }} pending
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>WhatsApp</th>
                    <th>Country</th>
                    <th>Expires</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
            @forelse($pendingInvitations as $invitation)
                <tr>
                    <td>
                        <div class="user-contact">
                            <strong>{{ $invitation->name }}</strong>
                            <span>{{ $invitation->email }}</span>
                        </div>
                    </td>
                    <td>{{ $invitation->whatsapp_number }}</td>
                    <td>{{ $invitation->country_code }}</td>
                    <td>{{ $invitation->expires_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ $invitation->createdByAdmin?->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="users-empty">
                        <strong>No pending admin invitations</strong>
                        <span>Use Add Customer to create a safe pre-onboarding record.</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:16px 20px;">
        <a class="secondary-btn" href="{{ route('admin.customers.onboarding') }}">
            View Onboarding Queue
        </a>
    </div>
</section>
@endsection
