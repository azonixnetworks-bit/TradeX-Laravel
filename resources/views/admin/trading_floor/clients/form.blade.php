@extends('admin.layouts.app')

@php
    $isEdit = $mode === 'edit';
    $pageTitle = $isEdit ? 'Edit Trading Floor Customer' : 'Register Trading Floor Customer';
@endphp

@section('title', $pageTitle.' | TradeX Administration')
@section('page-title', $pageTitle)

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · {{ $isEdit ? 'CUSTOMER MAINTENANCE' : 'CUSTOMER REGISTRATION' }}</div>
        <h1>{{ $isEdit ? 'Edit' : 'Register' }} <span>Customer</span></h1>
        <p>Maintain floor allocation, identity/contact information and the customer-specific commission agreement.</p>
    </div>
    <a class="btn btn-secondary" href="{{ $isEdit ? route('admin.trading_floor.clients.show', $client) : route('admin.trading_floor.clients.index') }}">Cancel</a>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-top:18px;">
        <strong>Please correct the highlighted fields.</strong>
        <ul style="margin:8px 0 0 18px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.trading_floor.clients.update', $client) : route('admin.trading_floor.clients.store') }}" style="margin-top:22px;">
    @csrf
    @if($isEdit) @method('PATCH') @endif

    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">PERSONAL DETAILS</div><h2>Customer Profile</h2></div></div>
        <div class="panel-body">
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input id="full_name" name="full_name" type="text" required maxlength="255" value="{{ old('full_name', $client->full_name) }}">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $client->email) }}">
                </div>
                <div class="form-group">
                    <label for="mobile">Mobile</label>
                    <input id="mobile" name="mobile" type="text" maxlength="40" value="{{ old('mobile', $client->mobile) }}">
                </div>
                <div class="form-group">
                    <label for="whatsapp_number">WhatsApp Number</label>
                    <input id="whatsapp_number" name="whatsapp_number" type="text" maxlength="40" value="{{ old('whatsapp_number', $client->whatsapp_number) }}">
                </div>
                <div class="form-group">
                    <label for="identity_type">Identity Type</label>
                    <select id="identity_type" name="identity_type">
                        <option value="">Select</option>
                        @foreach(['NIC', 'PASSPORT', 'OTHER'] as $option)
                            <option value="{{ $option }}" @selected(old('identity_type', $client->identity_type) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="identity_number">NIC / Passport Number</label>
                    <input id="identity_number" name="identity_number" type="text" maxlength="120" value="{{ old('identity_number', $client->identity_number) }}">
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $client->date_of_birth?->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label for="user_id">Linked TradeX User ID</label>
                    <input id="user_id" name="user_id" type="number" min="1" value="{{ old('user_id', $client->user_id) }}" placeholder="Optional existing user ID">
                </div>
            </div>
            <div class="form-group" style="margin-top:16px;">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3" maxlength="2000">{{ old('address', $client->address) }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                <div class="form-group">
                    <label for="emergency_contact_name">Emergency Contact Name</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" type="text" maxlength="255" value="{{ old('emergency_contact_name', $client->emergency_contact_name) }}">
                </div>
                <div class="form-group">
                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                    <input id="emergency_contact_phone" name="emergency_contact_phone" type="text" maxlength="40" value="{{ old('emergency_contact_phone', $client->emergency_contact_phone) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-header"><div><div class="panel-eyebrow">FLOOR OPERATIONS</div><h2>Allocation & Status</h2></div></div>
        <div class="panel-body">
            <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;">
                <div class="form-group">
                    <label for="joined_at">Joining Date</label>
                    <input id="joined_at" name="joined_at" type="date" value="{{ old('joined_at', $client->joined_at?->format('Y-m-d') ?: now()->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label for="floor_code">Floor</label>
                    <input id="floor_code" name="floor_code" type="text" maxlength="50" value="{{ old('floor_code', $client->floor_code) }}" placeholder="e.g. F1">
                </div>
                <div class="form-group">
                    <label for="desk_code">Desk / Seat</label>
                    <input id="desk_code" name="desk_code" type="text" maxlength="50" value="{{ old('desk_code', $client->desk_code) }}" placeholder="e.g. D12">
                </div>
                <div class="form-group">
                    <label for="package_code">Package</label>
                    <input id="package_code" name="package_code" type="text" maxlength="80" value="{{ old('package_code', $client->package_code) }}">
                </div>
            </div>
            <div class="form-group" style="max-width:280px;margin-top:16px;">
                <label for="status">Customer Status *</label>
                <select id="status" name="status" required>
                    @foreach(['ACTIVE', 'INACTIVE', 'SUSPENDED'] as $option)
                        <option value="{{ $option }}" @selected(old('status', $client->status ?: 'ACTIVE') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <div class="panel-header"><div><div class="panel-eyebrow">COMMISSION AGREEMENT</div><h2>Customer Commission Settings</h2></div></div>
        <div class="panel-body">
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;">
                <div class="form-group">
                    <label for="commission_type">Commission Type *</label>
                    <select id="commission_type" name="commission_type" required>
                        @foreach(['PERCENTAGE', 'FIXED', 'HYBRID'] as $option)
                            <option value="{{ $option }}" @selected(old('commission_type', $client->commission_type ?: 'PERCENTAGE') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="commission_rate">Percentage Rate</label>
                    <input id="commission_rate" name="commission_rate" type="number" min="0" max="100" step="0.0001" value="{{ old('commission_rate', $client->commission_rate) }}" placeholder="e.g. 20">
                </div>
                <div class="form-group">
                    <label for="fixed_commission_amount">Fixed Amount</label>
                    <input id="fixed_commission_amount" name="fixed_commission_amount" type="number" min="0" step="0.01" value="{{ old('fixed_commission_amount', $client->fixed_commission_amount) }}">
                </div>
                <div class="form-group">
                    <label for="commission_currency">Currency *</label>
                    <input id="commission_currency" name="commission_currency" type="text" required minlength="3" maxlength="3" value="{{ old('commission_currency', $client->commission_currency ?: 'LKR') }}">
                </div>
                <div class="form-group">
                    <label for="billing_cycle">Billing Cycle *</label>
                    <select id="billing_cycle" name="billing_cycle" required>
                        @foreach(['WEEKLY', 'MONTHLY', 'QUARTERLY'] as $option)
                            <option value="{{ $option }}" @selected(old('billing_cycle', $client->billing_cycle ?: 'MONTHLY') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="grace_days">Grace Days *</label>
                    <input id="grace_days" name="grace_days" type="number" min="0" max="365" required value="{{ old('grace_days', $client->grace_days ?? 0) }}">
                </div>
            </div>
            <p style="margin:14px 0 0;opacity:.68;font-size:13px;">Percentage uses trading profit × rate. Fixed uses the configured amount. Hybrid uses both. Invoice generation and payment reconciliation remain separate audited records.</p>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">
        <a class="btn btn-secondary" href="{{ $isEdit ? route('admin.trading_floor.clients.show', $client) : route('admin.trading_floor.clients.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Save Changes' : 'Register Customer' }}</button>
    </div>
</form>
@endsection
