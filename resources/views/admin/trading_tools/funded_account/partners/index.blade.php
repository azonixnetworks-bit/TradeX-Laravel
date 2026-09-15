@extends('admin.layouts.app')

@section('title', 'Funded Account Partners | TradeX Administration')
@section('page-title', 'Funded Account Partners')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">TRADING TOOLS · PARTNERS</div>
            <h1>Funded Account <span>Partners</span></h1>
            <p>Manage TradeX-recommended funded-account providers, referral links, offers and customer-PDF affiliate disclosures.</p>
        </div>
        <div class="funded-advisor-header-actions">
            <a class="secondary-btn" href="{{ route('admin.funded_advisor.index') }}">Advisor</a>
            <a class="primary-btn" href="{{ route('admin.funded_advisor.partners.create') }}">Add Partner</a>
        </div>
    </div>

    <section class="panel funded-advisor-partner-table-panel">
        <div class="funded-advisor-panel-heading">
            <div>
                <div class="panel-eyebrow">PARTNER DIRECTORY</div>
                <h2>Configured Providers</h2>
            </div>
            <span>{{ $partners->total() }}</span>
        </div>

        <div class="funded-advisor-table-wrap">
            <table class="funded-advisor-table">
                <thead>
                    <tr>
                        <th>Partner</th>
                        <th>Offer</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reports</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($partners as $partner)
                    <tr>
                        <td>
                            <div class="funded-advisor-partner-name">
                                @if($partner->hasLogo())
                                    <img src="{{ route('admin.funded_advisor.partners.logo', $partner) }}" alt="" width="40" height="40">
                                @else
                                    <span>{{ strtoupper(substr($partner->name, 0, 1)) }}</span>
                                @endif
                                <div>
                                    <strong>{{ $partner->name }}</strong>
                                    @if($partner->is_featured)<small>RECOMMENDED</small>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong>{{ $partner->promo_code ?: '—' }}</strong>
                            <small>{{ $partner->offer_text ?: 'No offer text' }}</small>
                        </td>
                        <td>{{ $partner->priority }}</td>
                        <td><span class="funded-advisor-status-pill {{ $partner->is_active ? 'is-safe' : 'is-muted' }}">{{ $partner->is_active ? 'ACTIVE' : 'INACTIVE' }}</span></td>
                        <td>{{ $partner->reports_count }}</td>
                        <td><a class="secondary-btn" href="{{ route('admin.funded_advisor.partners.edit', $partner) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="funded-advisor-empty">No funded-account partners configured yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $partners->links() }}
    </section>
</div>
@endsection
