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
        <a class="primary-btn" href="#add-bank-account">Add Bank Account</a>
        <a class="primary-btn" href="#add-usdt-wallet">Add USDT Wallet</a>
        <a class="secondary-btn" href="#add-other-crypto-wallet">Add Other Crypto</a>
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

    $activeUsdt = $receivingAccounts
        ->filter(fn ($account) => $account->isUsdtTrc20())
        ->where('is_active', true)
        ->count();

    $activeOtherCrypto = $receivingAccounts
        ->filter(fn ($account) => $account->isCrypto() && ! $account->isUsdtTrc20())
        ->where('is_active', true)
        ->count();
@endphp

<div class="user-stat-grid">
    <div class="user-stat-card"><span>PayPal</span><strong>{{ $accounts['paypal']['enabled'] ? 'Enabled' : 'Disabled' }}</strong></div>
    <div class="user-stat-card"><span>Active Banks</span><strong>{{ $activeBanks }}</strong></div>
    <div class="user-stat-card"><span>Active USDT TRC20</span><strong>{{ $activeUsdt }}</strong></div>
    <div class="user-stat-card"><span>Active Other Crypto</span><strong>{{ $activeOtherCrypto }}</strong></div>
</div>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-eyebrow">PAYPAL</div>
    <h2>Provider Configuration</h2>
    <p>Environment: <strong>{{ strtoupper($accounts['paypal']['environment']) }}</strong> · Driver: <strong>{{ $accounts['paypal']['driver'] }}</strong></p>
    <p>Countries: {{ implode(', ', $accounts['paypal']['countries']) ?: 'None' }}</p>
    <p>Client ID: {{ $accounts['paypal']['client_id_configured'] ? 'Configured' : 'Missing' }} · Client Secret: {{ $accounts['paypal']['client_secret_configured'] ? 'Configured' : 'Missing' }} · Webhook: {{ $accounts['paypal']['webhook_configured'] ? 'Configured' : 'Missing' }}</p>
</section>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-eyebrow">OWNER RECEIVING DESTINATIONS</div>
    <h2>Add the accounts customers will pay to</h2>
    <p>These are TradeX receiving destinations that you control. Add your bank account, your USDT TRC20 wallet, and any additional approved crypto wallet here. New destinations default to inactive until you explicitly enable them.</p>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px;margin-bottom:18px;">
    <section class="panel" id="add-bank-account" style="padding:22px;scroll-margin-top:24px;">
        <div class="panel-eyebrow">LOCAL BANK</div>
        <h2>Add Bank Account</h2>
        <p>Add the TradeX bank account that customers should transfer money to. Account number is encrypted at rest.</p>
        <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}">
            @csrf
            <input type="hidden" name="type" value="BANK">
            <label>Display Name</label><input name="display_name" placeholder="TradeX Commercial Bank" required maxlength="120">
            <label>Country Code</label><input name="country_code" value="LK" required maxlength="2">
            <label>Currency</label><input name="currency" value="LKR" required maxlength="12">
            <label>Amount Decimal Scale</label><input type="number" name="amount_scale" value="2" min="0" max="18" required>
            <label>Exact Customer Payment Amount</label><input name="tender_amount" value="25000.00" required maxlength="80">
            <label>Bank Name</label><input name="bank_name" placeholder="Commercial Bank" required maxlength="120">
            <label>Branch</label><input name="bank_branch" placeholder="Colombo" required maxlength="120">
            <label>Account Holder Name</label><input name="account_holder" placeholder="TradeX / Account Holder" required maxlength="160">
            <label>Account Number</label><input name="account_number" placeholder="Enter receiving account number" required maxlength="191" autocomplete="off">
            <label>Customer Instructions</label><textarea name="instructions" maxlength="2000" placeholder="Example: Use the TradeX payment reference in the transfer description."></textarea>
            <label>Display Order</label><input type="number" name="display_order" value="10" min="0" max="10000" required>
            <input type="hidden" name="is_active" value="0">
            <label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="is_active" value="1" style="width:auto;"> Active for future customer checkout</label>
            <button class="primary-btn" type="submit" style="margin-top:16px;">Save Bank Account</button>
        </form>
    </section>

    <section class="panel" id="add-usdt-wallet" style="padding:22px;scroll-margin-top:24px;">
        <div class="panel-eyebrow">USDT TRC20</div>
        <h2>Add USDT Wallet</h2>
        <p>Add your TradeX USDT receiving wallet. This dedicated form locks the coin/network contract to <strong>USDT / TRC20</strong> so customers are not accidentally shown the wrong network.</p>
        <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="CRYPTO">
            <input type="hidden" name="currency" value="USDT">
            <input type="hidden" name="coin_name" value="Tether">
            <input type="hidden" name="coin_symbol" value="USDT">
            <input type="hidden" name="network" value="TRC20">
            <label>Display Name</label><input name="display_name" value="USDT TRC20" required maxlength="120">
            <label>Country Code</label><input name="country_code" value="LK" required maxlength="2">
            <label>Amount Decimal Scale</label><input type="number" name="amount_scale" value="6" min="0" max="18" required>
            <label>Exact Customer Payment Amount (USDT)</label><input name="tender_amount" value="79.000000" required maxlength="80">
            <label>USDT TRC20 Wallet Address</label><input name="wallet_address" placeholder="T..." required maxlength="255" autocomplete="off">
            <label>Wallet QR Image (PNG/JPG/WEBP, max 2 MB)</label><input type="file" name="qr_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
            <label>Customer Instructions</label><textarea name="instructions" maxlength="2000">Send only USDT using the TRC20 network. Sending another token or network may cause permanent loss.</textarea>
            <label>Display Order</label><input type="number" name="display_order" value="20" min="0" max="10000" required>
            <input type="hidden" name="is_active" value="0">
            <label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="is_active" value="1" style="width:auto;"> Active for future customer checkout</label>
            <button class="primary-btn" type="submit" style="margin-top:16px;">Save USDT Wallet</button>
        </form>
    </section>

    <section class="panel" id="add-other-crypto-wallet" style="padding:22px;scroll-margin-top:24px;">
        <div class="panel-eyebrow">OTHER CRYPTO</div>
        <h2>Add Other Crypto Wallet</h2>
        <p>Use this only for another approved coin/network such as USDC, BTC, ETH or BNB. Do not use this form for USDT TRC20; use the dedicated USDT form above.</p>
        <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="CRYPTO">
            <label>Display Name</label><input name="display_name" placeholder="USDC Polygon" required maxlength="120">
            <label>Country Code</label><input name="country_code" value="LK" required maxlength="2">
            <label>Currency / Tender Symbol</label><input name="currency" placeholder="USDC" required maxlength="12">
            <label>Amount Decimal Scale</label><input type="number" name="amount_scale" value="6" min="0" max="18" required>
            <label>Exact Customer Payment Amount</label><input name="tender_amount" placeholder="79.000000" required maxlength="80">
            <label>Coin Name</label><input name="coin_name" placeholder="USD Coin" required maxlength="80">
            <label>Coin Symbol</label><input name="coin_symbol" placeholder="USDC" required maxlength="20">
            <label>Network</label><input name="network" placeholder="POLYGON" required maxlength="60">
            <label>Wallet Address</label><input name="wallet_address" placeholder="Enter receiving wallet address" required maxlength="255" autocomplete="off">
            <label>Wallet QR Image (PNG/JPG/WEBP, max 2 MB)</label><input type="file" name="qr_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
            <label>Customer Instructions</label><textarea name="instructions" maxlength="2000" placeholder="State the exact token and network the customer must use."></textarea>
            <label>Display Order</label><input type="number" name="display_order" value="30" min="0" max="10000" required>
            <input type="hidden" name="is_active" value="0">
            <label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="is_active" value="1" style="width:auto;"> Active for future customer checkout</label>
            <button class="primary-btn" type="submit" style="margin-top:16px;">Save Other Crypto Wallet</button>
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
                    @if ($receivingAccount->isBank())
                        <p>{{ $receivingAccount->bank_name }} · {{ $receivingAccount->bank_branch }} · {{ $receivingAccount->account_holder }}</p>
                    @else
                        <p>{{ $receivingAccount->coin_symbol }} · {{ $receivingAccount->network }}{{ $receivingAccount->isUsdtTrc20() ? ' · Dedicated USDT TRC20' : '' }}</p>
                    @endif
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
