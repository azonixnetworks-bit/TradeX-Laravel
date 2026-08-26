@extends('admin.layouts.app')

@section('title', 'KYC Verification')
@section('page-title', 'KYC Verification')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COMPLIANCE REVIEW</div>
        <h1>KYC <span>Verification</span></h1>
        <p>Review immutable user identity submissions and protected evidence.</p>
    </div>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Total</span><strong>{{ number_format($stats['total']) }}</strong></div>
    <div class="user-stat-card"><span>Pending Review</span><strong class="text-gold">{{ number_format($stats['pending']) }}</strong></div>
    <div class="user-stat-card"><span>Verified</span><strong class="text-green">{{ number_format($stats['verified']) }}</strong></div>
    <div class="user-stat-card"><span>Rejected</span><strong>{{ number_format($stats['rejected']) }}</strong></div>
    <div class="user-stat-card"><span>Needs Resubmission</span><strong>{{ number_format($stats['needs_resubmission']) }}</strong></div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.kyc.index') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Search</label>
            <input type="text" name="search" value="{{ request('search') }}" maxlength="191" placeholder="Submission UUID, user UUID, name or email">
        </div>

        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Country</label>
            <select name="country">
                <option value="">All</option>
                @foreach($countries as $code => $label)
                    <option value="{{ $code }}" @selected(request('country') === $code)>{{ $code }} — {{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Document Type</label>
            <select name="document_type">
                <option value="">All</option>
                @foreach($document_types as $type => $label)
                    <option value="{{ $type }}" @selected(request('document_type') === $type)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Submitted From</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>

        <div class="filter-item">
            <label>Submitted To</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50,100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.kyc.index') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">KYC QUEUE</div>
            <h2>Identity Submissions</h2>
        </div>
        <div class="panel-count">{{ number_format($submissions->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Submission</th>
                    <th>User</th>
                    <th>Country</th>
                    <th>Document</th>
                    <th>Evidence</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Reviewed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($submissions as $submission)
                <tr>
                    <td><strong>{{ $submission->uuid }}</strong></td>
                    <td>{{ $submission->user?->name ?? '—' }}<br><small>{{ $submission->user?->email ?? '—' }}</small></td>
                    <td>{{ $submission->country_code }}</td>
                    <td>{{ $submission->document_type }}</td>
                    <td>{{ number_format($submission->documents_count) }}</td>
                    <td><strong>{{ $submission->status }}</strong></td>
                    <td>{{ $submission->submitted_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    <td>
                        @if($submission->reviewed_at)
                            {{ $submission->reviewed_at->format('Y-m-d H:i:s') }}<br>
                            <small>{{ $submission->reviewedByAdmin?->name ?? '—' }}</small>
                        @else
                            —
                        @endif
                    </td>
                    <td><a class="secondary-btn" href="{{ route('admin.kyc.show', $submission->getKey()) }}">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="9">No KYC submissions match the selected filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">{{ $submissions->links() }}</div>
</section>
@endsection
