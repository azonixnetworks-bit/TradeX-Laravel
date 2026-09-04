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
                <label for="country_search">Country</label>

                @php
                    $selectedCountryCode = old('country_code');
                    $selectedCountryName = $selectedCountryCode
                        ? ($countries[$selectedCountryCode] ?? '')
                        : '';
                @endphp

                <div
                    class="country-combobox"
                    id="countryCombobox"
                    data-country-combobox
                >
                    <div class="country-search-shell">
                        <input
                            id="country_search"
                            class="country-search-input"
                            type="text"
                            value="{{ $selectedCountryName }}"
                            placeholder="Type a country name or code"
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-controls="countrySearchResults"
                            aria-expanded="false"
                            aria-haspopup="listbox"
                            required
                        >

                        <span
                            class="country-search-chevron"
                            aria-hidden="true"
                        >⌄</span>
                    </div>

                    <input
                        id="country_code"
                        type="hidden"
                        name="country_code"
                        value="{{ $selectedCountryCode }}"
                    >

                    <div
                        id="countrySearchResults"
                        class="country-search-results"
                        role="listbox"
                        aria-label="Country search results"
                        hidden
                    ></div>
                </div>

                <small class="form-help">
                    Start typing, for example “Sri”, “India”, “United” or an ISO country code.
                </small>

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

@push('styles')
<style>
    .country-combobox {
        position: relative;
    }

    .country-search-shell {
        position: relative;
    }

    .country-search-input {
        width: 100%;
        padding-right: 44px !important;
    }

    .country-search-chevron {
        position: absolute;
        top: 50%;
        right: 16px;
        transform: translateY(-50%);
        color: var(--text-muted, #7890a8);
        pointer-events: none;
        font-size: 18px;
        line-height: 1;
    }

    .country-search-results {
        position: absolute;
        z-index: 70;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        max-height: 300px;
        overflow-y: auto;
        padding: 6px;
        border: 1px solid rgba(125, 162, 196, 0.22);
        border-radius: 12px;
        background: #071726;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.36);
    }

    .country-search-results[hidden] {
        display: none;
    }

    .country-search-option {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 11px 12px;
        border: 0;
        border-radius: 9px;
        background: transparent;
        color: #d9e6f2;
        text-align: left;
        cursor: pointer;
        font: inherit;
    }

    .country-search-option:hover,
    .country-search-option.is-active {
        background: rgba(23, 112, 255, 0.16);
    }

    .country-search-option-code {
        flex: 0 0 auto;
        color: #7e98ad;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
    }

    .country-search-empty {
        padding: 12px;
        color: #7e98ad;
        font-size: 13px;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-country-combobox]');

    if (!root) {
        return;
    }

    const input = root.querySelector('#country_search');
    const hidden = root.querySelector('#country_code');
    const results = root.querySelector('#countrySearchResults');

    const countries = {{ \Illuminate\Support\Js::from($countryOptions) }};

    let filtered = [];
    let activeIndex = -1;

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const closeResults = () => {
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    };

    const selectCountry = (country) => {
        input.value = country.name;
        hidden.value = country.code;
        closeResults();
    };

    const render = () => {
        const query = normalize(input.value);

        filtered = countries.filter((country) => {
            const name = normalize(country.name);
            const code = normalize(country.code);

            return query === ''
                || name.includes(query)
                || code.includes(query);
        }).slice(0, 14);

        results.innerHTML = '';
        activeIndex = -1;

        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'country-search-empty';
            empty.textContent = 'No matching country found.';
            results.appendChild(empty);
        } else {
            filtered.forEach((country, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'country-search-option';
                option.setAttribute('role', 'option');
                option.dataset.index = String(index);

                const name = document.createElement('span');
                name.textContent = country.name;

                const code = document.createElement('span');
                code.className = 'country-search-option-code';
                code.textContent = country.code;

                option.appendChild(name);
                option.appendChild(code);

                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                });

                option.addEventListener('click', () => {
                    selectCountry(country);
                });

                results.appendChild(option);
            });
        }

        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const moveActive = (direction) => {
        if (results.hidden || filtered.length === 0) {
            render();
        }

        if (filtered.length === 0) {
            return;
        }

        activeIndex += direction;

        if (activeIndex < 0) {
            activeIndex = filtered.length - 1;
        }

        if (activeIndex >= filtered.length) {
            activeIndex = 0;
        }

        results
            .querySelectorAll('.country-search-option')
            .forEach((option, index) => {
                option.classList.toggle(
                    'is-active',
                    index === activeIndex
                );
            });
    };

    input.addEventListener('focus', render);

    input.addEventListener('input', () => {
        hidden.value = '';
        render();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveActive(1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveActive(-1);
            return;
        }

        if (
            event.key === 'Enter'
            && activeIndex >= 0
            && filtered[activeIndex]
        ) {
            event.preventDefault();
            selectCountry(filtered[activeIndex]);
            return;
        }

        if (event.key === 'Escape') {
            closeResults();
        }
    });

    input.addEventListener('blur', () => {
        const typed = normalize(input.value);

        if (typed !== '') {
            const exact = countries.find((country) =>
                normalize(country.name) === typed
                || normalize(country.code) === typed
            );

            if (exact) {
                selectCountry(exact);
                return;
            }
        }

        window.setTimeout(closeResults, 120);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closeResults();
        }
    });
});
</script>
@endpush
