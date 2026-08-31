@extends('admin.layouts.app')

@section('title', 'TradeX Payment Accounts')
@section('page-title', 'Payment Accounts')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COMPANY PAYMENT CONFIGURATION</div>
        <h1>Payment <span>Accounts</span></h1>
        <p>Manage customer-facing bank destinations and crypto wallets. Provider secrets remain deployment-managed and are never exposed here.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.payments.crypto.index') }}">Other Crypto Review</a>
        <a class="secondary-btn" href="{{ route('admin.payments.index') }}">Payments</a>
    </div>
</div>

@if (session('success'))
    <div class="panel" style="padding:14px 18px;margin-bottom:18px;">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="panel" style="padding:14px 18px;margin-bottom:18px;">
        <strong>Unable to save the receiving account.</strong>
        <ul style="margin:10px 0 0 20px;">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@php
    $activeBanks = $receivingAccounts
        ->where('type', \App\Models\PaymentReceivingAccount::TYPE_BANK)
        ->where('is_active', true)
        ->count();

    $activeCrypto = $receivingAccounts
        ->where('type', \App\Models\PaymentReceivingAccount::TYPE_CRYPTO)
        ->where('is_active', true)
        ->count();
@endphp

<div class="user-stat-grid">
    <div class="user-stat-card"><span>PayPal</span><strong>{{ $accounts['paypal']['enabled'] ? 'Enabled' : 'Disabled' }}</strong></div>
    <div class="user-stat-card"><span>Active Banks</span><strong>{{ $activeBanks }}</strong></div>
    <div class="user-stat-card"><span>Active Crypto Wallets</span><strong>{{ $activeCrypto }}</strong></div>
</div>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-eyebrow">PAYPAL</div>
    <h2>Provider Configuration</h2>
    <p>Environment: <strong>{{ strtoupper($accounts['paypal']['environment']) }}</strong> · Driver: <strong>{{ $accounts['paypal']['driver'] }}</strong></p>
    <p>Countries: {{ implode(', ', $accounts['paypal']['countries']) ?: 'None' }}</p>
    <p>Client ID: {{ $accounts['paypal']['client_id_configured'] ? 'Configured' : 'Missing' }} · Client Secret: {{ $accounts['paypal']['client_secret_configured'] ? 'Configured' : 'Missing' }} · Webhook: {{ $accounts['paypal']['webhook_configured'] ? 'Configured' : 'Missing' }}</p>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;margin-bottom:18px;">
    <section class="panel" style="padding:22px;">
        <div class="panel-eyebrow">LOCAL BANK</div>
        <h2>Add Bank Account</h2>
        <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}">
            @csrf
            <input type="hidden" name="type" value="BANK">
            <label>Display Name</label><input name="display_name" required maxlength="120">
            <label>Country Code</label><input name="country_code" value="LK" required maxlength="2">
            <label>Currency</label><input name="currency" value="LKR" required maxlength="12">
            <label>Amount Decimal Scale</label><input type="number" name="amount_scale" value="2" min="0" max="18" required>
            <label>Exact Customer Payment Amount</label><input name="tender_amount" value="25000.00" required maxlength="80">
            <label>Bank Name</label><input name="bank_name" required maxlength="120">
            <label>Branch</label><input name="bank_branch" required maxlength="120">
            <label>Account Holder Name</label><input name="account_holder" required maxlength="160">
            <label>Account Number</label><input name="account_number" required maxlength="191" autocomplete="off">
            <label>Customer Instructions</label><textarea name="instructions" maxlength="2000"></textarea>
            <label>Display Order</label><input type="number" name="display_order" value="100" min="0" max="10000" required>
            <input type="hidden" name="is_active" value="0">
            <label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="is_active" value="1" style="width:auto;"> Active for future customer checkout</label>
            <button class="primary-btn" type="submit" style="margin-top:16px;">Add Bank Account</button>
        </form>
    </section>

    <section class="panel" style="padding:22px;">
        <div class="panel-eyebrow">CRYPTO</div>
        <h2>Add Crypto Wallet</h2>
        <p><strong>Binance / USDT TRC20</strong> remains supported alongside other configured crypto coins and networks.</p>
        <p>Configure USDT, USDC, BTC, ETH, BNB or another supported coin/network. Decimal scale is the exact precision TradeX will accept for the configured tender amount (0–18).</p>
        <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="CRYPTO">
            <label>Display Name</label><input name="display_name" required maxlength="120">
            <label>Country Code</label><input name="country_code" value="IN" required maxlength="2">
            <label>Currency / Tender Symbol</label><input name="currency" value="USDT" required maxlength="12">
            <label>Amount Decimal Scale</label><input type="number" name="amount_scale" value="6" min="0" max="18" required>
            <label>Exact Customer Payment Amount</label><input name="tender_amount" value="79.000000" required maxlength="80">
            <label>Coin Name</label><input name="coin_name" value="Tether" required maxlength="80">
            <label>Coin Symbol</label><input name="coin_symbol" value="USDT" required maxlength="20">
            <label>Network</label><input name="network" value="TRC20" required maxlength="60">
            <label>Wallet Address</label><input name="wallet_address" required maxlength="255" autocomplete="off">
            <label>QR Image (PNG/JPG/WEBP, max 2 MB)</label><input type="file" name="qr_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
            <label>Customer Instructions</label><textarea name="instructions" maxlength="2000"></textarea>
            <label>Display Order</label><input type="number" name="display_order" value="100" min="0" max="10000" required>
            <input type="hidden" name="is_active" value="0">
            <label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="is_active" value="1" style="width:auto;"> Active for future customer checkout</label>
            <button class="primary-btn" type="submit" style="margin-top:16px;">Add Crypto Wallet</button>
        </form>
    </section>
</div>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-eyebrow">MANAGED DESTINATIONS</div>
    <h2>Receiving Accounts</h2>
    <p style="margin-bottom:18px;">Deactivate instead of deleting. Prepared payment orders keep an encrypted destination snapshot.</p>

    @forelse ($receivingAccounts as $receivingAccount)
        @php
            $scale = (int) ($receivingAccount->amount_scale ?? 2);
            $displayAmount = (string) ($receivingAccount->tender_amount_decimal ?? '');

            if ($displayAmount === '') {
                $minor = (int) ($receivingAccount->tender_amount_minor ?? 0);
                $safeScale = min(max($scale, 0), 8);
                $factor = 10 ** $safeScale;
                $whole = intdiv($minor, $factor);
                $displayAmount = (string) $whole;

                if ($safeScale > 0) {
                    $fraction = str_pad(
                        (string) ($minor % $factor),
                        $safeScale,
                        '0',
                        STR_PAD_LEFT
                    );
                    $displayAmount .= '.'.$fraction;
                }
            }
        @endphp
        <div style="border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:18px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                <div>
                    <div class="panel-eyebrow">{{ $receivingAccount->type }}</div>
                    <h3 style="margin:4px 0;">{{ $receivingAccount->display_name }}</h3>
                    <p>{{ $receivingAccount->country_code }} · {{ $receivingAccount->currency }} {{ $displayAmount }} · Order {{ $receivingAccount->display_order }} · <strong>{{ $receivingAccount->is_active ? 'ACTIVE' : 'INACTIVE' }}</strong></p>
                </div>
                @if ($receivingAccount->qr_path)<a class="secondary-btn" href="{{ route('admin.payments.accounts.receiving.qr', $receivingAccount) }}">View QR</a>@endif
            </div>

            <form method="POST" action="{{ route('admin.payments.accounts.receiving.update', $receivingAccount) }}" enctype="multipart/form-data" style="margin-top:14px;">
                @csrf
                @method('PATCH')
                <input type="hidden" name="type" value="{{ $receivingAccount->type }}">
                <label>Display Name</label><input name="display_name" value="{{ $receivingAccount->display_name }}" required maxlength="120">
                <label>Country Code</label><input name="country_code" value="{{ $receivingAccount->country_code }}" required maxlength="2">
                <label>Currency</label><input name="currency" value="{{ $receivingAccount->currency }}" required maxlength="12">
                <label>Amount Decimal Scale</label><input type="number" name="amount_scale" value="{{ $scale }}" min="0" max="18" required>
                <label>Exact Customer Payment Amount</label><input name="tender_amount" value="{{ $displayAmount }}" required maxlength="80">
                @if ($receivingAccount->isBank())
                    <label>Bank Name</label><input name="bank_name" value="{{ $receivingAccount->bank_name }}" required maxlength="120">
                    <label>Branch</label><input name="bank_branch" value="{{ $receivingAccount->bank_branch }}" required maxlength="120">
                    <label>Account Holder</label><input name="account_holder" value="{{ $receivingAccount->account_holder }}" required maxlength="160">
                    <label>Account Number</label><input name="account_number" value="{{ $receivingAccount->account_number }}" required maxlength="191" autocomplete="off">
                @else
                    <label>Coin Name</label><input name="coin_name" value="{{ $receivingAccount->coin_name }}" required maxlength="80">
                    <label>Coin Symbol</label><input name="coin_symbol" value="{{ $receivingAccount->coin_symbol }}" required maxlength="20">
                    <label>Network</label><input name="network" value="{{ $receivingAccount->network }}" required maxlength="60">
                    <label>Wallet Address</label><input name="wallet_address" value="{{ $receivingAccount->wallet_address }}" required maxlength="255" autocomplete="off">
                    <label>Replace QR Image</label><input type="file" name="qr_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                    @if ($receivingAccount->qr_path)
                        <input type="hidden" name="remove_qr" value="0">
                        <label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="remove_qr" value="1" style="width:auto;"> Remove current QR image</label>
                    @endif
                @endif
                <label>Customer Instructions</label><textarea name="instructions" maxlength="2000">{{ $receivingAccount->instructions }}</textarea>
                <label>Display Order</label><input type="number" name="display_order" value="{{ $receivingAccount->display_order }}" min="0" max="10000" required>
                <input type="hidden" name="is_active" value="0">
                <label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="is_active" value="1" style="width:auto;" {{ $receivingAccount->is_active ? 'checked' : '' }}> Active</label>
                <button class="primary-btn" type="submit" style="margin-top:16px;">Save Changes</button>
            </form>
        </div>
    @empty
        <p>No database-managed receiving accounts yet.</p>
    @endforelse
</section>
@endsection
