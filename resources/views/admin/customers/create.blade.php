@extends('admin.layouts.app')

@section('title', 'Add TradeX Customer')
@section('page-title', 'Add Customer')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">CUSTOMER ONBOARDING</div>
        <h1>Add <span>Customer</span></h1>
        <p>
            Create a secure pre-onboarding invitation without creating
            credentials, TOTP secrets, premium access, or payment state.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.customers.dashboard') }}"
    >
        Back to Customers
    </a>
</div>

<div class="admin-form-callout">
    <div class="admin-form-callout-icon" aria-hidden="true">◇</div>
    <div>
        <strong>Security boundary</strong>
        This action does not create an active customer account. The customer
        must complete the normal TradeX registration flow, verification and
        TOTP setup. Premium access remains payment/entitlement controlled.
    </div>
</div>

<section class="panel admin-form-panel">
    <div class="admin-form-heading">
        <div class="panel-eyebrow">CUSTOMER DETAILS</div>
        <h2>Pre-onboarding information</h2>
        <p>
            Use the customer&apos;s real contact details. Email and WhatsApp are
            later reconciled against the customer&apos;s completed registration.
        </p>
    </div>

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
                    placeholder="Customer full name"
                >
                @error('name')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    maxlength="255"
                    required
                    value="{{ old('email') }}"
                    autocomplete="email"
                    placeholder="customer@example.com"
                >
                @error('email')
                    <small class="form-error">{{ $message }}</small>
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
                <small class="form-help">
                    E.164 international format including the + country code.
                </small>
                @error('whatsapp_number')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="country_code">Country</label>
                <select
                    id="country_code"
                    name="country_code"
                    required
                >
                    <option value="">Select country</option>
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
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="language_code">Preferred Language</label>
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
                    <small class="form-error">{{ $message }}</small>
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
                    autocomplete="off"
                >
                <small class="form-help">
                    Only an existing ACTIVE TradeX customer referral code is accepted.
                </small>
                @error('referral_code')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="internal_note">Internal Admin Note</label>
                <textarea
                    id="internal_note"
                    name="internal_note"
                    maxlength="500"
                    rows="4"
                    placeholder="Optional operational note. Do not store passwords, OTPs, TOTP secrets or payment credentials."
                >{{ old('internal_note') }}</textarea>
                <small class="form-help">
                    Internal operational context only. Sensitive credentials must never be stored here.
                </small>
                @error('internal_note')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>
        </div>

        <div class="admin-form-note">
            <span aria-hidden="true">◆</span>
            <span>
                <strong>Initial account type: FREE.</strong>
                The customer receives account access only after completing the
                normal registration flow. Admin onboarding cannot grant PREMIUM.
            </span>
        </div>

        <div class="admin-form-actions">
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
