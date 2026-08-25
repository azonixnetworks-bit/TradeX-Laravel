@extends('admin.layouts.app')

@section('title', 'Affiliate Referral Tree')
@section('page-title', 'Referral Tree')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">DIRECT REFERRALS ONLY</div>
        <h1>{{ $affiliate->name }} <span>Referral Tree</span></h1>
        <p>Commissionable scope is single-level/direct-sale only. No upline, generation or team commission is shown.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.affiliates.show', $affiliate) }}">Back to Affiliate</a>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.affiliates.tree', $affiliate) }}" class="user-filter-form">
        <div class="filter-search"><label>Search direct referrals</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, referral code..."></div>
        <div class="filter-item"><label>Per Page</label><select name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int) request('per_page',20) === $size)>{{ $size }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="primary-btn" type="submit">Apply</button><a class="secondary-btn" href="{{ route('admin.affiliates.tree', $affiliate) }}">Reset</a></div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header"><div><div class="panel-eyebrow">{{ $scope }}</div><h2>Direct Referrals</h2></div><div class="panel-count">{{ number_format($referrals->total()) }}</div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>User</th><th>Referral Code</th><th>Account</th><th>Status</th><th>Cleared Qualified Sales</th><th>Lifetime Recorded Sales</th><th>Latest Qualified</th><th>Joined</th></tr></thead>
            <tbody>
            @forelse($referrals as $referral)
                @php $qualification = $qualifiedByReferral->get($referral->id); @endphp
                <tr>
                    <td><strong>{{ $referral->name }}</strong><br><small>{{ $referral->email }}</small></td>
                    <td>{{ $referral->referral_code ?: '—' }}</td>
                    <td>{{ $referral->account_type ?? '—' }}</td>
                    <td>{{ $referral->status ?? '—' }}</td>
                    <td>{{ number_format((int) ($qualification?->cleared_count ?? 0)) }}</td>
                    <td>{{ number_format((int) ($qualification?->lifetime_count ?? 0)) }}</td>
                    <td>{{ $qualification?->latest_qualified_at ?? '—' }}</td>
                    <td>{{ $referral->created_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No direct referrals found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:18px;">{{ $referrals->links() }}</div>
</section>
@endsection
