@extends('admin.layouts.app')

@section('title', 'Add Copy Trading Broker')
@section('page-title', 'Add Broker')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · BROKER ONBOARDING</div>
        <h1>Add <span>Broker</span></h1>
        <p>
            Create the broker and its first supported market configuration.
            TradeX partner URLs remain editable from Admin.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.brokers.index') }}"
    >
        Back to Brokers
    </a>
</div>

<div class="admin-form-callout">
    <div class="admin-form-callout-icon" aria-hidden="true">◆</div>
    <div>
        <strong>Eligibility boundary</strong>
        Broker onboarding is LIVE-account only. Verification must later confirm
        TradeX partner attribution and the configured eligible currency.
    </div>
</div>

<section class="panel admin-form-panel">
    <div class="admin-form-heading">
        <div class="panel-eyebrow">BROKER CONFIGURATION</div>
        <h2>Broker and first market</h2>
    </div>

    <form
        method="POST"
        action="{{ route('admin.copy_trading.brokers.store') }}"
        enctype="multipart/form-data"
    >
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Broker Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    maxlength="120"
                    required
                    value="{{ old('name') }}"
                    placeholder="IC Markets"
                >
                @error('name')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label for="code">Broker Code</label>
                <input
                    id="code"
                    type="text"
                    name="code"
                    maxlength="40"
                    required
                    value="{{ old('code') }}"
                    placeholder="IC_MARKETS"
                    autocomplete="off"
                >
                <small class="form-help">Uppercase letters, numbers, underscore or hyphen.</small>
                @error('code')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label for="logo">Broker Logo</label>
                <input
                    id="logo"
                    type="file"
                    name="logo"
                    accept="image/jpeg,image/png,image/webp"
                >
                <small class="form-help">Optional JPG, PNG or WebP. Maximum 2 MB.</small>
                @error('logo')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label>Broker Status</label>
                <input type="hidden" name="is_active" value="0">
                <label style="display:flex;gap:10px;align-items:center;">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked((bool) old('is_active', true))
                    >
                    Active for customer onboarding
                </label>
                @error('is_active')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label for="market_category">Market Category</label>
                <select id="market_category" name="market_category" required>
                    @foreach($marketCategories as $market)
                        <option
                            value="{{ $market }}"
                            @selected(old('market_category') === $market)
                        >
                            {{ $market }}
                        </option>
                    @endforeach
                </select>
                @error('market_category')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label for="required_currency">Eligible Account Currency</label>
                <input
                    id="required_currency"
                    type="text"
                    name="required_currency"
                    maxlength="10"
                    required
                    value="{{ old('required_currency', $requiredCurrency) }}"
                >
                @error('required_currency')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group full-width">
                <label for="referral_url">IB / Referral Link</label>
                <input
                    id="referral_url"
                    type="url"
                    name="referral_url"
                    maxlength="2048"
                    required
                    value="{{ old('referral_url') }}"
                    placeholder="https://official-broker.example/partner/..."
                    autocomplete="off"
                >
                <small class="form-help">
                    HTTPS only. This URL is returned to the customer only after
                    they choose OPEN ACCOUNT VIA TRADEX.
                </small>
                @error('referral_url')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group full-width">
                <label>Market Status</label>
                <input type="hidden" name="market_is_active" value="0">
                <label style="display:flex;gap:10px;align-items:center;">
                    <input
                        type="checkbox"
                        name="market_is_active"
                        value="1"
                        @checked((bool) old('market_is_active', true))
                    >
                    Allow onboarding for this market
                </label>
                @error('market_is_active')<small class="form-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="admin-form-note">
            <span aria-hidden="true">◇</span>
            <span>
                <strong>LIVE account only.</strong>
                This policy is server-enforced during verification and is not an
                editable broker setting.
            </span>
        </div>

        <div class="admin-form-actions">
            <button class="primary-btn" type="submit">
                Create Broker
            </button>
            <a
                class="secondary-btn"
                href="{{ route('admin.copy_trading.brokers.index') }}"
            >
                Cancel
            </a>
        </div>
    </form>
</section>
@endsection
