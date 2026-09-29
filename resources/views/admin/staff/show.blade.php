@extends('admin.layouts.app')

@section('title', 'Staff Review')
@section('page-title', 'Staff Management')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">STAFF REVIEW</div>
        <h1>{{ $staffMember->full_name }}</h1>
        <p>{{ $staffMember->staff_number }} · {{ $staffMember->job_role }} · {{ $staffMember->employment_status }}</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.staff.index') }}">Back to staff</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<div class="panel" style="padding:24px">
    <div class="page-eyebrow">ONBOARDING</div>
    <h2>Identity, contact and KYC</h2>
    <p>Email: <strong>{{ $staffMember->email_verified_at ? 'Verified' : 'Pending' }}</strong> · WhatsApp: <strong>{{ $staffMember->whatsapp_verified_at ? 'Verified' : 'Pending' }}</strong> · KYC: <strong>{{ $staffMember->kyc_verified_at ? 'Approved' : 'Pending review' }}</strong></p>

    <div class="table-wrap" style="margin-top:16px">
        <table>
            <thead><tr><th>Document</th><th>Submitted</th><th>Status</th><th>Reviewer note</th></tr></thead>
            <tbody>
            @forelse($staffMember->kycDocuments as $document)
                <tr><td>{{ str_replace('_', ' ', $document->document_type) }}</td><td>{{ $document->created_at?->format('Y-m-d H:i') }}</td><td>{{ $document->status }}</td><td>{{ $document->review_note ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="4">No KYC documents submitted.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:20px">
        @foreach(['EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'] as $channel => $label)
            @php($verified = $channel === 'EMAIL' ? $staffMember->email_verified_at : $staffMember->whatsapp_verified_at)
            <div class="panel" style="padding:16px">
                <h3>{{ $label }} verification</h3>
                <p>{{ $verified ? 'Verified' : 'Not verified' }}</p>
                @unless($verified)
                    <form method="POST" action="{{ route('admin.staff.otp.send', [$staffMember, $channel]) }}">
                        @csrf
                        <button class="secondary-btn" type="submit">Send code</button>
                    </form>
                    <form method="POST" action="{{ route('admin.staff.otp.verify', [$staffMember, $channel]) }}" style="display:flex;gap:8px;margin-top:10px">
                        @csrf
                        <input name="code" inputmode="numeric" maxlength="6" placeholder="6-digit code" required>
                        <button class="primary-btn" type="submit">Verify</button>
                    </form>
                @endunless
            </div>
        @endforeach
    </div>

    @if(auth('admin')->user()->isSuperAdmin())
        <form method="POST" action="{{ route('admin.staff.kyc.review', $staffMember) }}" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:20px;align-items:end">
            @csrf @method('PATCH')
            <label>Status<select name="status"><option value="APPROVED">Approve KYC</option><option value="REJECTED">Reject KYC</option></select></label>
            <label style="flex:1;min-width:220px">Review note<input name="note" placeholder="Optional review note"></label>
            <button class="primary-btn" type="submit">Save KYC review</button>
        </form>
    @endif
</div>

<div class="panel" style="padding:24px;margin-top:16px">
    <div class="page-eyebrow">PAYROLL</div>
    <h2>Salary records</h2>
    @if(auth('admin')->user()->isSuperAdmin())
        <form method="POST" action="{{ route('admin.staff.salary.store', $staffMember) }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:16px 0">
            @csrf
            <label>Pay period<input type="month" name="period" required></label>
            <label>Gross amount<input type="number" step="0.01" min="0" name="gross_amount" required></label>
            <label>Deductions<input type="number" step="0.01" min="0" name="deduction_amount" value="0"></label>
            <label>Currency<input name="currency" value="USD" maxlength="3" required></label>
            <label>Paid at<input type="datetime-local" name="paid_at"></label>
            <label>Reference<input name="reference" placeholder="Payment reference"></label>
            <div><button class="primary-btn" type="submit">Record salary</button></div>
        </form>
    @endif
    <div class="table-wrap"><table><thead><tr><th>Period</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead><tbody>
        @forelse($staffMember->salaryPayments as $salary)
            <tr><td>{{ $salary->period }}</td><td>{{ $salary->currency }} {{ $salary->gross_amount }}</td><td>{{ $salary->currency }} {{ $salary->deduction_amount }}</td><td>{{ $salary->currency }} {{ $salary->net_amount }}</td><td>{{ $salary->status }}</td></tr>
        @empty<tr><td colspan="5">No salary records.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="panel" style="padding:24px;margin-top:16px">
    <div class="page-eyebrow">LEAVE MANAGEMENT</div>
    <h2>Leave requests</h2>
    <div class="table-wrap"><table><thead><tr><th>Period</th><th>Type</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($staffMember->leaveRequests as $leave)
        <tr><td>{{ $leave->starts_on?->format('Y-m-d') }} – {{ $leave->ends_on?->format('Y-m-d') }}</td><td>{{ $leave->leave_type }}</td><td>{{ $leave->reason }}</td><td>{{ $leave->status }}</td><td>
            @if($leave->status === 'PENDING' && (auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->hasRole('SUPPORT_ADMIN')))
                <form method="POST" action="{{ route('admin.staff.leave.review', $leave) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf @method('PATCH')
                    <select name="status"><option value="APPROVED">Approve</option><option value="REJECTED">Reject</option></select><input name="review_note" placeholder="Review note"><button class="primary-btn" type="submit">Save</button>
                </form>
            @else
                {{ $leave->review_note ?: '—' }}
            @endif
        </td></tr>
    @empty<tr><td colspan="5">No leave requests.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="panel" style="padding:24px;margin-top:16px">
    <div class="page-eyebrow">PAYOUT SECURITY</div>
    <h2>Locked payout details</h2>
    <p>Bank and USDT details stay private. Staff can request a change; only a Super Admin can approve it.</p>
    <div class="table-wrap"><table><thead><tr><th>Requested</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($staffMember->payoutChangeRequests as $change)
        <tr><td>{{ $change->created_at?->format('Y-m-d H:i') }}</td><td>{{ $change->reason }}</td><td>{{ $change->status }}</td><td>
            @if($change->status === 'PENDING' && auth('admin')->user()->isSuperAdmin())
                <form method="POST" action="{{ route('admin.staff.payout-change.review', $change) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf @method('PATCH')
                    <select name="status"><option value="APPROVED">Approve</option><option value="REJECTED">Reject</option></select><input name="review_note" placeholder="Review note"><button class="primary-btn" type="submit">Save</button>
                </form>
            @else
                {{ $change->review_note ?: '—' }}
            @endif
        </td></tr>
    @empty<tr><td colspan="4">No payout change requests.</td></tr>@endforelse
    </tbody></table></div>
</div>

@if(auth('admin')->user()->isSuperAdmin() && $staffMember->employment_status !== 'TERMINATED')
<div class="panel" style="padding:24px;margin-top:16px">
    <div class="page-eyebrow">TERMINATION</div>
    <h2>Issue termination agreement</h2>
    <form method="POST" action="{{ route('admin.staff.termination.start', $staffMember) }}">
        @csrf
        <label>Termination agreement content<textarea name="content" rows="7" required>Termination agreement for {{ $staffMember->full_name }}. Please review and acknowledge this agreement.</textarea></label>
        <button class="danger-btn" type="submit" style="margin-top:12px">Send termination agreement</button>
    </form>
</div>
@endif
@endsection
