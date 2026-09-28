@extends('admin.layouts.app')
@section('title','Add Staff')
@section('page-title','Add Staff')
@section('content')
<div class="page-header"><div><div class="page-eyebrow">STAFF ONBOARDING</div><h1>Add <span>Staff</span></h1><p>Portal access remains disabled until verification, KYC and agreement acceptance.</p></div><a class="secondary-btn" href="{{ route('admin.staff.index') }}">Back</a></div>
<div class="panel" style="padding:24px"><form method="POST" action="{{ route('admin.staff.store') }}">@csrf
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<div class="form-grid"><label>Full Name<input name="full_name" required value="{{ old('full_name') }}"></label><label>Email<input type="email" name="email" required value="{{ old('email') }}"></label><label>WhatsApp Number<input name="whatsapp_number" value="{{ old('whatsapp_number') }}"></label><label>Job Role<input name="job_role" required value="{{ old('job_role') }}"></label><label>Department<input name="department" value="{{ old('department') }}"></label><label>Joining Date<input type="date" name="joined_on" value="{{ old('joined_on') }}"></label><label>Password<input type="password" name="password" required></label><label>Confirm Password<input type="password" name="password_confirmation" required></label></div><button class="primary-btn" type="submit">Create Staff Profile</button></form></div>
@endsection
