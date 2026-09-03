@extends('admin.layouts.app')

@section('title', 'Investment Agreements')
@section('page-title', 'Investment Agreements')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">PRIVATE LEGAL DOCUMENTS</div>
        <h1>Investment <span>Agreements</span></h1>
        <p>Generated versions and signed PDFs are kept in private storage with SHA-256 integrity hashes. Signed versions are never overwritten.</p>
    </div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">AGREEMENT LEDGER</div>
            <h2>{{ number_format($agreements->total()) }} agreement versions</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Investment</th>
                <th>Customer</th>
                <th>Version</th>
                <th>Template</th>
                <th>Risk Version</th>
                <th>Status</th>
                <th>Signed</th>
            </tr>
            </thead>
            <tbody>
            @forelse($agreements as $agreement)
                <tr>
                    <td>
                        <a href="{{ route('admin.investments.show', $agreement->investment) }}">
                            {{ $agreement->investment?->investment_code }}
                        </a>
                    </td>
                    <td>{{ $agreement->investment?->user?->name ?? '—' }}</td>
                    <td>v{{ $agreement->version }}</td>
                    <td>{{ $agreement->template_version ?? '—' }}</td>
                    <td>{{ $agreement->risk_disclosure_version ?? '—' }}</td>
                    <td>{{ $agreement->status }}</td>
                    <td>{{ $agreement->signed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No investment agreements generated yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:18px;">
        {{ $agreements->links() }}
    </div>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">AWAITING AGREEMENT</div>
            <h2>Customer contracts requiring completion</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Investment</th>
                <th>Customer</th>
                <th>Latest Agreement</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($awaitingInvestments as $investment)
                <tr>
                    <td>{{ $investment->investment_code }}</td>
                    <td>{{ $investment->user?->name ?? '—' }}</td>
                    <td>{{ $investment->latestAgreement?->status ?? 'NOT GENERATED' }}</td>
                    <td>
                        <a class="secondary-btn" href="{{ route('admin.investments.show', $investment) }}">
                            Open Workflow
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No investments are waiting for agreement completion.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
