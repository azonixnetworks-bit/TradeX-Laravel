@extends('admin.layouts.app')

@section('title', 'All Investments')
@section('page-title', 'All Investments')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">INVESTMENT LEDGER</div>
        <h1>All <span>Investments</span></h1>
        <p>Customer investment records with product, term, agreement and lifecycle visibility.</p>
    </div>

    @if($canCreate)
        <a class="primary-btn" href="{{ route('admin.investments.create') }}">Add Investment</a>
    @endif
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.investments.index') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Search</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Investment code, customer name or email">
        </div>

        <div class="filter-item">
            <label>Status</label>
            <input type="text" name="status" value="{{ request('status') }}" placeholder="ACTIVE">
        </div>

        <div class="filter-item">
            <label>Product</label>
            <select name="product">
                <option value="">All Products</option>
                @foreach($products as $product)
                    <option value="{{ $product->code }}" @selected(request('product') === $product->code)>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Filter</button>
            <a class="secondary-btn" href="{{ route('admin.investments.index') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">INVESTMENT RECORDS</div>
            <h2>{{ number_format($investments->total()) }} records</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Investment</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Deposit</th>
                <th>Period</th>
                <th>Profit Share</th>
                <th>Agreement</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($investments as $investment)
                <tr>
                    <td>
                        <a href="{{ route('admin.investments.show', $investment) }}">
                            <strong>{{ $investment->investment_code }}</strong>
                        </a>
                    </td>
                    <td>
                        {{ $investment->user?->name ?? '—' }}<br>
                        <small>{{ $investment->user?->email ?? '—' }}</small>
                    </td>
                    <td>{{ $investment->product?->name ?? '—' }}</td>
                    <td>{{ $investment->deposit_currency }} {{ number_format((float) $investment->deposit_amount, 2) }}</td>
                    <td>{{ $investment->investment_period_years }} year(s)</td>
                    <td>
                        {{ $investment->customer_profit_share_bps / 100 }}% /
                        {{ $investment->company_profit_share_bps / 100 }}%
                    </td>
                    <td>{{ $investment->latestAgreement?->status ?? 'NOT CREATED' }}</td>
                    <td>{{ $investment->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No investments matched the filter.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:18px;">
        {{ $investments->links() }}
    </div>
</section>
@endsection
