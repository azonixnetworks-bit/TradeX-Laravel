@extends('admin.layouts.app')

@section('title', 'Configure Copy Trading Broker')
@section('page-title', 'Configure Broker')

@section('content')
@php
    $configuredMarkets = $broker->markets->pluck('market_category')->all();
    $availableMarkets = array_values(array_diff($marketCategories, $configuredMarkets));
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · BROKER CONTROL PLANE</div>
        <h1>{{ $broker->name }} <span>Configuration</span></h1>
        <p>
            Change broker availability and partner URLs without a code deploy.
            Existing broker-account history remains unchanged.
        </p>
    </div>

    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.brokers.index') }}"
    >
        Back to Brokers
    </a>
</div>

@if($broker->hasLogo())
    <section class="panel" style="padding:16px;margin-bottom:18px;">
        <img
            src="{{ route('admin.copy_trading.brokers.logo', ['copyTradeBroker' => $broker]) }}"
            alt="{{ $broker->name }} logo"
            style="max-width:180px;max-height:80px;object-fit:contain;"
        >
    </section>
@endif

<section class="panel admin-form-panel" style="margin-bottom:20px;">
    <div class="admin-form-heading">
        <div class="panel-eyebrow">BROKER</div>
        <h2>General configuration</h2>
    </div>

    <form
        method="POST"
        action="{{ route('admin.copy_trading.brokers.update', ['copyTradeBroker' => $broker]) }}"
        enctype="multipart/form-data"
    >
        @csrf
        @method('PATCH')

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Broker Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    maxlength="120"
                    required
                    value="{{ old('name', $broker->name) }}"
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
                    value="{{ old('code', $broker->code) }}"
                >
                @error('code')<small class="form-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label for="logo">Replace Broker Logo</label>
                <input
                    id="logo"
                    type="file"
                    name="logo"
                    accept="image/jpeg,image/png,image/webp"
                >
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
                        @checked((bool) old('is_active', $broker->is_active))
                    >
                    Active for customer onboarding
                </label>
            </div>
        </div>

        <div class="admin-form-actions">
            <button class="primary-btn" type="submit">
                Save Broker
            </button>
        </div>
    </form>
</section>

@foreach($broker->markets as $market)
    <section class="panel admin-form-panel" style="margin-bottom:20px;">
        <div class="admin-form-heading">
            <div class="panel-eyebrow">MARKET CONFIGURATION</div>
            <h2>{{ $market->market_category }}</h2>
            <p>
                LIVE account only · Current onboarding currency:
                {{ $market->required_currency }}
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.copy_trading.brokers.markets.update', [
                'copyTradeBroker' => $broker,
                'copyTradeBrokerMarket' => $market,
            ]) }}"
        >
            @csrf
            @method('PATCH')

            <div class="form-grid">
                <div class="form-group">
                    <label>Market Category</label>
                    <input type="text" value="{{ $market->market_category }}" disabled>
                    <small class="form-help">Market category is immutable. Add a new market instead.</small>
                </div>

                <div class="form-group">
                    <label for="currency_{{ $market->id }}">Eligible Account Currency</label>
                    <input
                        id="currency_{{ $market->id }}"
                        type="text"
                        name="required_currency"
                        maxlength="10"
                        required
                        value="{{ old('required_currency', $market->required_currency) }}"
                    >
                </div>

                <div class="form-group full-width">
                    <label for="referral_{{ $market->id }}">IB / Referral Link</label>
                    <input
                        id="referral_{{ $market->id }}"
                        type="url"
                        name="referral_url"
                        maxlength="2048"
                        required
                        value="{{ old('referral_url', $market->referral_url) }}"
                    >
                </div>

                <div class="form-group full-width">
                    <label>Market Status</label>
                    <input type="hidden" name="is_active" value="0">
                    <label style="display:flex;gap:10px;align-items:center;">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            @checked((bool) old('is_active', $market->is_active))
                        >
                        Allow new TradeX partner onboarding for {{ $market->market_category }}
                    </label>
                </div>
            </div>

            <div class="admin-form-actions">
                <button class="primary-btn" type="submit">
                    Save {{ $market->market_category }} Configuration
                </button>
            </div>
        </form>
    </section>
@endforeach

@if($availableMarkets !== [])
    <section class="panel admin-form-panel">
        <div class="admin-form-heading">
            <div class="panel-eyebrow">ADD MARKET</div>
            <h2>Additional supported market</h2>
        </div>

        <form
            method="POST"
            action="{{ route('admin.copy_trading.brokers.markets.store', ['copyTradeBroker' => $broker]) }}"
        >
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label for="market_category">Market Category</label>
                    <select id="market_category" name="market_category" required>
                        @foreach($availableMarkets as $marketCategory)
                            <option value="{{ $marketCategory }}">
                                {{ $marketCategory }}
                            </option>
                        @endforeach
                    </select>
                    @error('market_category')<small class="form-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label for="new_required_currency">Eligible Account Currency</label>
                    <input
                        id="new_required_currency"
                        type="text"
                        name="required_currency"
                        maxlength="10"
                        required
                        value="{{ old('required_currency', $requiredCurrency) }}"
                    >
                </div>

                <div class="form-group full-width">
                    <label for="new_referral_url">IB / Referral Link</label>
                    <input
                        id="new_referral_url"
                        type="url"
                        name="referral_url"
                        maxlength="2048"
                        required
                        value="{{ old('referral_url') }}"
                    >
                </div>

                <div class="form-group full-width">
                    <input type="hidden" name="is_active" value="0">
                    <label style="display:flex;gap:10px;align-items:center;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Activate this market after saving
                    </label>
                </div>
            </div>

            <div class="admin-form-actions">
                <button class="primary-btn" type="submit">
                    Add Market
                </button>
            </div>
        </form>
    </section>
@endif
@endsection
