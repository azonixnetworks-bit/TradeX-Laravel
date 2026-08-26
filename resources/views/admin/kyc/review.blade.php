@extends('admin.layouts.app')

@section('title', 'KYC Review')
@section('page-title', 'KYC Review')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">KYC REVIEW</div>
        <h1>Identity <span>Evidence</span></h1>
        <p>{{ $submission->uuid }}</p>
    </div>

    <a class="secondary-btn" href="{{ route('admin.kyc.index') }}">Back to KYC Queue</a>
</div>

@if($errors->any())
    <section class="panel" style="padding:18px;margin-bottom:18px;">
        <strong>KYC review could not be completed.</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </section>
@endif

@if(session('success'))
    <section class="panel" style="padding:18px;margin-bottom:18px;">{{ session('success') }}</section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Status</span><strong>{{ $submission->status }}</strong></div>
    <div class="user-stat-card"><span>Country</span><strong>{{ $submission->country_code }}</strong><small>{{ $country_label }}</small></div>
    <div class="user-stat-card"><span>Document Type</span><strong>{{ $submission->document_type }}</strong><small>{{ $document_type_label }}</small></div>
    <div class="user-stat-card"><span>Submitted</span><strong>{{ $submission->submitted_at?->format('Y-m-d') ?? '—' }}</strong><small>{{ $submission->submitted_at?->format('H:i:s') ?? '' }}</small></div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">ACCOUNT</div><h2>User</h2></div>
    </div>
    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
                <tr><th>Name</th><td>{{ $submission->user?->name ?? '—' }}</td></tr>
                <tr><th>Email</th><td>{{ $submission->user?->email ?? '—' }}</td></tr>
                <tr><th>WhatsApp</th><td>{{ $submission->user?->whatsapp_number ?? '—' }}</td></tr>
                <tr><th>Account Type</th><td>{{ $submission->user?->account_type ?? '—' }}</td></tr>
                <tr><th>Account Status</th><td>{{ $submission->user?->status ?? '—' }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">IDENTITY SUMMARY</div><h2>Submitted Identity Data</h2></div>
        @if($permissions['view_documents'])
            <a class="secondary-btn" href="{{ route('admin.kyc.identity', $submission->getKey()) }}">Reveal Sensitive Identity</a>
        @endif
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <tbody>
                <tr><th>Legal Name</th><td>{{ $identity_summary['legal_name'] }}</td></tr>
                <tr><th>Date of Birth</th><td>{{ $identity_summary['date_of_birth'] }}</td></tr>
                <tr>
                    <th>Identity Number</th>
                    <td><strong>{{ $identity_summary['identity_number'] }}</strong><br><small>Masked by default. Full reveal is audited.</small></td>
                </tr>
                <tr><th>Address</th><td>{{ $identity_summary['address'] }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">PRIVATE EVIDENCE</div><h2>Documents</h2></div>
        <div class="panel-count">{{ number_format($submission->documents->count()) }} files</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr><th>Kind</th><th>Required</th><th>MIME Type</th><th>Size</th><th>Access</th></tr>
            </thead>
            <tbody>
            @forelse($submission->documents as $document)
                <tr>
                    <td><strong>{{ $document->document_kind }}</strong></td>
                    <td>{{ in_array($document->document_kind, $required_documents, true) ? 'Yes' : 'Optional' }}</td>
                    <td>{{ $document->mime_type }}</td>
                    <td>{{ number_format((int) $document->size_bytes) }} bytes</td>
                    <td>
                        @if($permissions['view_documents'])
                            <a class="secondary-btn"
                               target="_blank"
                               rel="noopener noreferrer"
                               href="{{ route('admin.kyc.document', [
                                    'kycSubmission' => $submission->getKey(),
                                    'kycDocument' => $document->getKey(),
                               ]) }}">View Evidence</a>
                        @else
                            Restricted
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No document evidence is attached.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if(
    $submission->status === \App\Models\KycSubmission::STATUS_REJECTED
    || $submission->status === \App\Models\KycSubmission::STATUS_NEEDS_RESUBMISSION
)
    <section class="panel" style="padding:22px;margin-bottom:18px;">
        <div class="panel-eyebrow">USER-VISIBLE REVIEW REASON</div>
        <p>{{ $submission->rejection_reason ?: '—' }}</p>
    </section>
@endif

@if($submission->reviewed_at)
    <section class="panel" style="padding:22px;margin-bottom:18px;">
        <div class="panel-eyebrow">REVIEW RECORD</div>
        <p><strong>Reviewed:</strong> {{ $submission->reviewed_at->format('Y-m-d H:i:s') }}</p>
        <p><strong>Reviewer:</strong> {{ $submission->reviewedByAdmin?->name ?? '—' }}</p>
        @if($submission->internal_notes)
            <p><strong>Internal notes:</strong> {{ $submission->internal_notes }}</p>
        @endif
    </section>
@endif

@if($submission->supersedes || $submission->supersededBy->isNotEmpty())
    <section class="panel" style="padding:22px;margin-bottom:18px;">
        <div class="panel-eyebrow">SUBMISSION HISTORY</div>
        @if($submission->supersedes)
            <p>Supersedes: <a href="{{ route('admin.kyc.show', $submission->supersedes->getKey()) }}">{{ $submission->supersedes->uuid }}</a> — {{ $submission->supersedes->status }}</p>
        @endif
        @foreach($submission->supersededBy as $newer)
            <p>Superseded by: <a href="{{ route('admin.kyc.show', $newer->getKey()) }}">{{ $newer->uuid }}</a> — {{ $newer->status }}</p>
        @endforeach
    </section>
@endif

@if($submission->isPendingReview())
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;">
        @if($permissions['approve'])
            <section class="panel" style="padding:22px;">
                <div class="panel-eyebrow">APPROVE</div>
                <h2>Verify KYC</h2>
                <p>Approval verifies required evidence existence and SHA-256 integrity before changing status.</p>
                <form method="POST" action="{{ route('admin.kyc.approve', $submission->getKey()) }}">
                    @csrf
                    @method('PATCH')
                    <label>Internal Notes (optional)</label>
                    <textarea name="internal_notes" rows="4" maxlength="2000">{{ old('internal_notes') }}</textarea>
                    <button class="primary-btn" type="submit">Verify KYC</button>
                </form>
            </section>
        @endif

        @if($permissions['request_resubmission'])
            <section class="panel" style="padding:22px;">
                <div class="panel-eyebrow">CORRECTION</div>
                <h2>Request Resubmission</h2>
                <p>Use when the user can correct or replace submitted evidence.</p>
                <form method="POST" action="{{ route('admin.kyc.request-resubmission', $submission->getKey()) }}">
                    @csrf
                    @method('PATCH')
                    <label>User-visible reason</label>
                    <textarea name="reason" rows="4" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
                    <label>Internal Notes (optional)</label>
                    <textarea name="internal_notes" rows="3" maxlength="2000">{{ old('internal_notes') }}</textarea>
                    <button class="secondary-btn" type="submit">Request Resubmission</button>
                </form>
            </section>
        @endif

        @if($permissions['reject'])
            <section class="panel" style="padding:22px;">
                <div class="panel-eyebrow">REJECT</div>
                <h2>Reject KYC</h2>
                <p>Use when the submission should not be approved. The reason is visible to the user.</p>
                <form method="POST" action="{{ route('admin.kyc.reject', $submission->getKey()) }}">
                    @csrf
                    @method('PATCH')
                    <label>User-visible reason</label>
                    <textarea name="reason" rows="4" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
                    <label>Internal Notes (optional)</label>
                    <textarea name="internal_notes" rows="3" maxlength="2000">{{ old('internal_notes') }}</textarea>
                    <button class="secondary-btn" type="submit">Reject KYC</button>
                </form>
            </section>
        @endif
    </div>
@else
    <section class="panel" style="padding:22px;">
        <div class="panel-eyebrow">IMMUTABLE REVIEW STATE</div>
        <p>This submission is no longer pending review. ADMIN-10 does not reopen or edit completed KYC decisions.</p>
    </section>
@endif
@endsection
