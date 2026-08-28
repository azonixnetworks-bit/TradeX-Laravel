@extends('admin.layouts.app')

@section('title', 'Add TradeX Customer')
@section('page-title', 'Add Customer')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">CUSTOMER ONBOARDING</div>
        <h1>Add <span>Customer</span></h1>
        <p>
            Create a pre-onboarding invitation without creating credentials,
            TOTP secrets, premium access, or payment state.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.customers.dashboard') }}"
    >
        Back to Customers
    </a>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <strong>Security boundary:</strong>
    this action does not create an active customer account. The customer must
    complete the normal TradeX registration flow, verification and TOTP setup.
    Premium access remains payment/entitlement controlled.
</section>

<section class="panel" style="padding:22px;">
    <form
        method="POST"
        action="{{ route('admin.customers.store') }}"
    >
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    maxlength="120"
                    required
                    value="{{ old('name') }}"
                    autocomplete="name"
                >
                @error('name')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    maxlength="255"
                    required
                    value="{{ old('email') }}"
                    autocomplete="email"
                >
                @error('email')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="whatsapp_number">WhatsApp Number</label>
                <input
                    id="whatsapp_number"
                    type="text"
                    name="whatsapp_number"
                    maxlength="30"
                    required
                    value="{{ old('whatsapp_number') }}"
                    placeholder="+94771234567"
                    autocomplete="tel"
                >
                <small>E.164 international format including + country code.</small>
                @error('whatsapp_number')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="country_code">Country</label>
                <select
                    id="country_code"
                    name="country_code"
                    required
                >
                    <option value="">Select</option>
                    @foreach([
                        'LK' => 'Sri Lanka',
                        'IN' => 'India',
                        'AE' => 'United Arab Emirates',
                        'MY' => 'Malaysia',
                    ] as $code => $label)
                        <option
                            value="{{ $code }}"
                            @selected(old('country_code') === $code)
                        >
                            {{ $label }} ({{ $code }})
                        </option>
                    @endforeach
                </select>
                @error('country_code')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="language_code">Language</label>
                <select
                    id="language_code"
                    name="language_code"
                    required
                >
                    @foreach($languages as $language)
                        <option
                            value="{{ $language }}"
                            @selected(old('language_code', 'en') === $language)
                        >
                            {{ strtoupper($language) }}
                        </option>
                    @endforeach
                </select>
                @error('language_code')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="referral_code">Referral Code</label>
                <input
                    id="referral_code"
                    type="text"
                    name="referral_code"
                    maxlength="30"
                    value="{{ old('referral_code') }}"
                    placeholder="Optional"
                >
                <small>Only an existing ACTIVE TradeX customer referral code is accepted.</small>
                @error('referral_code')
                    <small>{{ $message }}</small>
                @enderror
            </div>
        </div>

        <div class="form-group" style="margin-top:18px;">
            <label for="internal_note">Internal Admin Note</label>
            <textarea
                id="internal_note"
                name="internal_note"
                maxlength="500"
                rows="4"
                placeholder="Optional operational note. Do not store passwords, OTPs, TOTP secrets or payment credentials."
            >{{ old('internal_note') }}</textarea>
            @error('internal_note')
                <small>{{ $message }}</small>
            @enderror
        </div>

        <div
            class="panel"
            style="padding:14px;margin-top:18px;"
        >
            <strong>Initial account type:</strong> FREE after the customer
            completes normal registration. Admin onboarding cannot grant
            PREMIUM access.
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;">
            <button class="primary-btn" type="submit">
                Create Onboarding Invitation
            </button>

            <a
                class="secondary-btn"
                href="{{ route('admin.customers.onboarding') }}"
            >
                Pending Onboarding
            </a>
        </div>
    </form>
</section>
@endsection
