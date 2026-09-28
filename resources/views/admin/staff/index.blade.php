@extends('admin.layouts.app')
@section('title','Staff Management')
@section('page-title','Staff Management')
@section('content')
<div class="page-header"><div><div class="page-eyebrow">OPERATIONS WORKFORCE</div><h1>Staff <span>Management</span></h1><p>Employment, KYC, agreements, leave and salary records.</p></div><a class="primary-btn" href="{{ route('admin.staff.create') }}">Add Staff</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="panel"><div class="panel-header"><h2>Staff Directory</h2></div><div class="table-wrap"><table><thead><tr><th>Staff</th><th>Role</th><th>Contact Verification</th><th>Status</th><th>KYC</th></tr></thead><tbody>@forelse($staff as $member)<tr><td><strong>{{ $member->full_name }}</strong><br><small>{{ $member->staff_number }}</small></td><td>{{ $member->job_role }}<br><small>{{ $member->department }}</small></td><td>Email: {{ $member->email_verified_at ? 'Verified' : 'Pending' }}<br>WhatsApp: {{ $member->whatsapp_verified_at ? 'Verified' : 'Pending' }}</td><td>{{ $member->employment_status }}</td><td>{{ $member->kyc_verified_at ? 'Verified' : 'Pending' }}</td></tr>@empty<tr><td colspan="5">No staff profiles yet.</td></tr>@endforelse</tbody></table></div>{{ $staff->links() }}</div>
@endsection
