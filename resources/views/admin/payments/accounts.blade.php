@extends('admin.layouts.app')

@section('title', 'TradeX Payment Accounts')
@section('page-title', 'Payment Accounts')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/payment-accounts.css') }}">
@endpush

@section('content')
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

<div class="payment-accounts-page">
    <div class="page-header pa-page-header">
        <div class="pa-page-header-copy">
            <div class="page-eyebrow">COMPANY PAYMENT CONFIGURATION</div>
            <h1>Payment <span>Accounts</span></h1>
            <p>
                Manage the bank accounts and crypto wallets customers can pay to.
                Sensitive destination values stay encrypted at rest and provider secrets remain deployment-managed.
            </p>
        </div>

        <div class="pa-header-actions" aria-label="Payment account shortcuts">
            <a class="pa-action-link primary" href="#add-bank-account">＋ Add Bank</a>
            <a class="pa-action-link primary" href="#add-usdt-wallet">＋ Add USDT</a>
            <a class="pa-action-link" href="#add-other-crypto-wallet">＋ Other Crypto</a>
            <a class="pa-action-link" href="{{ route('admin.payments.index') }}">View Payments</a>
        </div>
    </div>

    @if (session('success'))
        <div class="pa-alert success" role="status">
            <strong>✓</strong>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="pa-alert error" role="alert">
            <strong>!</strong>
            <div>
                <strong>Unable to save the receiving account.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="pa-summary-grid">
        <div class="pa-summary-card">
            <div class="pa-summary-icon">P</div>
            <div class="pa-summary-copy">
                <span>PayPal Provider</span>
                <strong>{{ $accounts['paypal']['enabled'] ? 'Enabled' : 'Disabled' }}</strong>
                <small>{{ strtoupper($accounts['paypal']['environment']) }} environment</small>
            </div>
        </div>

        <div class="pa-summary-card">
            <div class="pa-summary-icon blue">B</div>
            <div class="pa-summary-copy">
                <span>Active Banks</span>
                <strong>{{ $activeBanks }}</strong>
                <small>Available for checkout</small>
            </div>
        </div>

        <div class="pa-summary-card">
            <div class="pa-summary-icon green">₮</div>
            <div class="pa-summary-copy">
                <span>Active USDT TRC20</span>
                <strong>{{ $activeUsdt }}</strong>
                <small>Dedicated TRC20 wallets</small>
            </div>
        </div>

        <div class="pa-summary-card">
            <div class="pa-summary-icon purple">C</div>
            <div class="pa-summary-copy">
                <span>Other Crypto</span>
                <strong>{{ $activeOtherCrypto }}</strong>
                <small>Configured coin / network pairs</small>
            </div>
        </div>
    </div>

    <section class="pa-provider-card" aria-label="PayPal provider configuration">
        <div class="pa-provider-title">
            <div class="panel-eyebrow">PAYPAL PROVIDER</div>
            <h2>Deployment configuration</h2>
        </div>

        <div class="pa-provider-item">
            <span>Environment</span>
            <strong>{{ strtoupper($accounts['paypal']['environment']) }}</strong>
        </div>

        <div class="pa-provider-item">
            <span>Countries</span>
            <strong>{{ implode(', ', $accounts['paypal']['countries']) ?: 'None' }}</strong>
        </div>

        <div class="pa-provider-item">
            <span>Client ID</span>
            <strong>{{ $accounts['paypal']['client_id_configured'] ? 'Configured' : 'Missing' }}</strong>
        </div>

        <div class="pa-provider-item">
            <span>Secret / Webhook</span>
            <strong>
                {{ $accounts['paypal']['client_secret_configured'] ? 'Secret ✓' : 'Secret missing' }}
                ·
                {{ $accounts['paypal']['webhook_configured'] ? 'Webhook ✓' : 'Webhook missing' }}
            </strong>
        </div>
    </section>

    <section class="pa-owner-guide">
        <div class="pa-owner-guide-icon">→</div>
        <div>
            <div class="panel-eyebrow">OWNER RECEIVING DESTINATIONS</div>
            <h2>Add the accounts customers will pay to</h2>
            <p>
                Add your TradeX bank account, your Binance / USDT TRC20 wallet, or another approved crypto wallet below.
                New destinations stay inactive until you explicitly enable them for future customer checkout.
            </p>
        </div>
    </section>

    <div class="pa-create-grid">
        <section class="pa-account-card bank" id="add-bank-account">
            <div class="pa-card-header">
                <div class="pa-card-icon">B</div>
                <div class="pa-card-heading">
                    <div class="panel-eyebrow">LOCAL BANK</div>
                    <h2>Add Bank Account</h2>
                    <p>Add the TradeX bank destination customers should transfer funds to.</p>
                </div>
                <span class="pa-card-badge">Encrypted at rest</span>
            </div>

            <div class="pa-card-body">
                <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}">
                    @csrf
                    <input type="hidden" name="type" value="BANK">

                    <div class="pa-form-grid">
                        <div class="form-group span-2">
                            <label for="bank_display_name">Display Name</label>
                            <input id="bank_display_name" name="display_name" placeholder="TradeX Commercial Bank" required maxlength="120">
                        </div>

                        <div class="form-group">
                            <label for="bank_country_code">Country Code</label>
                            <input id="bank_country_code" name="country_code" value="LK" required maxlength="2">
                        </div>

                        <div class="form-group">
                            <label for="bank_currency">Currency</label>
                            <input id="bank_currency" name="currency" value="LKR" required maxlength="12">
                        </div>

                        <div class="form-group">
                            <label for="bank_amount_scale">Amount Decimal Scale</label>
                            <input id="bank_amount_scale" type="number" name="amount_scale" value="2" min="0" max="18" required>
                            <span class="pa-field-help">Bank settlement supports the configured minor-unit precision.</span>
                        </div>

                        <div class="form-group">
                            <label for="bank_tender_amount">Exact Customer Payment Amount</label>
                            <input id="bank_tender_amount" name="tender_amount" value="25000.00" inputmode="decimal" required maxlength="80">
                        </div>

                        <div class="form-group">
                            <label for="bank_name">Bank Name</label>
                            <input id="bank_name" name="bank_name" placeholder="Commercial Bank" required maxlength="120">
                        </div>

                        <div class="form-group">
                            <label for="bank_branch">Branch</label>
                            <input id="bank_branch" name="bank_branch" placeholder="Colombo" required maxlength="120">
                        </div>

                        <div class="form-group">
                            <label for="bank_account_holder">Account Holder Name</label>
                            <input id="bank_account_holder" name="account_holder" placeholder="TradeX / Account Holder" required maxlength="160">
                        </div>

                        <div class="form-group">
                            <label for="bank_account_number">Account Number</label>
                            <input id="bank_account_number" name="account_number" placeholder="Enter receiving account number" required maxlength="191" autocomplete="off">
                        </div>

                        <div class="form-group span-2">
                            <label for="bank_instructions">Customer Instructions</label>
                            <textarea id="bank_instructions" name="instructions" maxlength="2000" placeholder="Example: Use the TradeX payment reference in the transfer description."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="bank_display_order">Display Order</label>
                            <input id="bank_display_order" type="number" name="display_order" value="10" min="0" max="10000" required>
                        </div>
                    </div>

                    <input type="hidden" name="is_active" value="0">
                    <div class="pa-toggle-row">
                        <div class="pa-toggle-copy">
                            <strong>Active for future customer checkout</strong>
                            <span>Enable only after the bank details are verified.</span>
                        </div>
                        <label class="pa-switch" aria-label="Activate bank account">
                            <input type="checkbox" name="is_active" value="1">
                            <span class="pa-switch-track"></span>
                        </label>
                    </div>

                    <div class="pa-form-actions">
                        <span class="pa-security-note">Account number is encrypted before storage.</span>
                        <button class="primary-btn" type="submit">Save Bank Account</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="pa-account-card usdt" id="add-usdt-wallet">
            <div class="pa-card-header">
                <div class="pa-card-icon usdt">₮</div>
                <div class="pa-card-heading">
                    <div class="panel-eyebrow">Binance / USDT TRC20</div>
                    <h2>Add USDT Wallet</h2>
                    <p>Dedicated USDT / TRC20 destination with the token and network contract locked.</p>
                </div>
                <span class="pa-card-badge">TRC20 locked</span>
            </div>

            <div class="pa-card-body">
                <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="CRYPTO">
                    <input type="hidden" name="currency" value="USDT">
                    <input type="hidden" name="coin_name" value="Tether">
                    <input type="hidden" name="coin_symbol" value="USDT">
                    <input type="hidden" name="network" value="TRC20">

                    <div class="pa-form-grid">
                        <div class="form-group span-2">
                            <label for="usdt_display_name">Display Name</label>
                            <input id="usdt_display_name" name="display_name" value="USDT TRC20" required maxlength="120">
                        </div>

                        <div class="form-group">
                            <label for="usdt_country_code">Country Code</label>
                            <input id="usdt_country_code" name="country_code" value="LK" required maxlength="2">
                        </div>

                        <div class="form-group">
                            <label for="usdt_amount_scale">Amount Decimal Scale</label>
                            <input id="usdt_amount_scale" type="number" name="amount_scale" value="6" min="0" max="18" required>
                        </div>

                        <div class="form-group span-2">
                            <label for="usdt_tender_amount">Exact Customer Payment Amount (USDT)</label>
                            <input id="usdt_tender_amount" name="tender_amount" value="79.000000" inputmode="decimal" required maxlength="80">
                        </div>

                        <div class="form-group span-2">
                            <label for="usdt_wallet_address">USDT TRC20 Wallet Address</label>
                            <div class="pa-locked-field">
                                <input id="usdt_wallet_address" name="wallet_address" placeholder="T..." required maxlength="255" autocomplete="off">
                                <span class="pa-locked-note">TRC20</span>
                            </div>
                        </div>

                        <div class="form-group span-2">
                            <label for="usdt_qr_image">Wallet QR Image (PNG/JPG/WEBP, max 2 MB)</label>
                            <input id="usdt_qr_image" type="file" name="qr_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                        </div>

                        <div class="form-group span-2">
                            <label for="usdt_instructions">Customer Instructions</label>
                            <textarea id="usdt_instructions" name="instructions" maxlength="2000">Send only USDT using the TRC20 network. Sending another token or network may cause permanent loss.</textarea>
                        </div>

                        <div class="form-group">
                            <label for="usdt_display_order">Display Order</label>
                            <input id="usdt_display_order" type="number" name="display_order" value="20" min="0" max="10000" required>
                        </div>
                    </div>

                    <input type="hidden" name="is_active" value="0">
                    <div class="pa-toggle-row">
                        <div class="pa-toggle-copy">
                            <strong>Active for future customer checkout</strong>
                            <span>Enable only after wallet address and network are verified.</span>
                        </div>
                        <label class="pa-switch" aria-label="Activate USDT wallet">
                            <input type="checkbox" name="is_active" value="1">
                            <span class="pa-switch-track"></span>
                        </label>
                    </div>

                    <div class="pa-form-actions">
                        <span class="pa-security-note">Wallet address and protected QR metadata remain secured.</span>
                        <button class="primary-btn" type="submit">Save USDT Wallet</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="pa-account-card crypto" id="add-other-crypto-wallet">
            <div class="pa-card-header">
                <div class="pa-card-icon crypto">C</div>
                <div class="pa-card-heading">
                    <div class="panel-eyebrow">OTHER CRYPTO</div>
                    <h2>Add Other Crypto Wallet</h2>
                    <p>Configure another approved coin/network such as USDC, BTC, ETH or BNB. Use the dedicated form above for USDT TRC20.</p>
                </div>
                <span class="pa-card-badge">Up to 18 decimals</span>
            </div>

            <div class="pa-card-body">
                <form method="POST" action="{{ route('admin.payments.accounts.receiving.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="CRYPTO">

                    <div class="pa-form-grid three">
                        <div class="form-group">
                            <label for="crypto_display_name">Display Name</label>
                            <input id="crypto_display_name" name="display_name" placeholder="USDC Polygon" required maxlength="120">
                        </div>

                        <div class="form-group">
                            <label for="crypto_country_code">Country Code</label>
                            <input id="crypto_country_code" name="country_code" value="LK" required maxlength="2">
                        </div>

                        <div class="form-group">
                            <label for="crypto_currency">Currency / Tender Symbol</label>
                            <input id="crypto_currency" name="currency" placeholder="USDC" required maxlength="12">
                        </div>

                        <div class="form-group">
                            <label for="crypto_amount_scale">Amount Decimal Scale</label>
                            <input id="crypto_amount_scale" type="number" name="amount_scale" value="6" min="0" max="18" required>
                        </div>

                        <div class="form-group">
                            <label for="crypto_tender_amount">Exact Customer Payment Amount</label>
                            <input id="crypto_tender_amount" name="tender_amount" placeholder="79.000000" inputmode="decimal" required maxlength="80">
                        </div>

                        <div class="form-group">
                            <label for="crypto_coin_name">Coin Name</label>
                            <input id="crypto_coin_name" name="coin_name" placeholder="USD Coin" required maxlength="80">
                        </div>

                        <div class="form-group">
                            <label for="crypto_coin_symbol">Coin Symbol</label>
                            <input id="crypto_coin_symbol" name="coin_symbol" placeholder="USDC" required maxlength="20">
                        </div>

                        <div class="form-group">
                            <label for="crypto_network">Network</label>
                            <input id="crypto_network" name="network" placeholder="POLYGON" required maxlength="60">
                        </div>

                        <div class="form-group">
                            <label for="crypto_wallet_address">Wallet Address</label>
                            <input id="crypto_wallet_address" name="wallet_address" placeholder="Enter receiving wallet address" required maxlength="255" autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="crypto_qr_image">Wallet QR Image (PNG/JPG/WEBP, max 2 MB)</label>
                            <input id="crypto_qr_image" type="file" name="qr_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                        </div>

                        <div class="form-group span-2">
                            <label for="crypto_instructions">Customer Instructions</label>
                            <textarea id="crypto_instructions" name="instructions" maxlength="2000" placeholder="State the exact token and network the customer must use."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="crypto_display_order">Display Order</label>
                            <input id="crypto_display_order" type="number" name="display_order" value="30" min="0" max="10000" required>
                        </div>
                    </div>

                    <input type="hidden" name="is_active" value="0">
                    <div class="pa-toggle-row">
                        <div class="pa-toggle-copy">
                            <strong>Active for future customer checkout</strong>
                            <span>Verify coin, network, exact amount and wallet before enabling.</span>
                        </div>
                        <label class="pa-switch" aria-label="Activate crypto wallet">
                            <input type="checkbox" name="is_active" value="1">
                            <span class="pa-switch-track"></span>
                        </label>
                    </div>

                    <div class="pa-form-actions">
                        <span class="pa-security-note">No crypto FX conversion is invented; configured tender remains exact.</span>
                        <button class="primary-btn" type="submit">Save Other Crypto Wallet</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <section class="panel pa-managed-section">
        <div class="pa-managed-header">
            <div>
                <div class="panel-eyebrow">MANAGED DESTINATIONS</div>
                <h2>Receiving Accounts</h2>
                <p>Update or deactivate existing destinations. Prepared payment orders keep their immutable encrypted destination snapshot.</p>
            </div>
            <span class="pa-managed-count">{{ $receivingAccounts->count() }} configured</span>
        </div>

        @if ($receivingAccounts->isNotEmpty())
            <div class="pa-destination-list">
                @foreach ($receivingAccounts as $receivingAccount)
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

                    <article class="pa-destination-card">
                        <div class="pa-destination-head">
                            <div>
                                <div class="pa-destination-title-row">
                                    <h3>{{ $receivingAccount->display_name }}</h3>
                                    <span class="pa-type-pill">
                                        {{ $receivingAccount->isBank() ? 'Bank' : ($receivingAccount->isUsdtTrc20() ? 'USDT TRC20' : 'Crypto') }}
                                    </span>
                                    <span class="pa-status-pill {{ $receivingAccount->is_active ? 'active' : 'inactive' }}">
                                        {{ $receivingAccount->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>

                                <div class="pa-destination-meta">
                                    <span>{{ $receivingAccount->country_code }}</span>
                                    <span>{{ $receivingAccount->currency }} {{ $displayAmount }}</span>
                                    <span>Order {{ $receivingAccount->display_order }}</span>
                                    @if ($receivingAccount->isBank())
                                        <span>{{ $receivingAccount->bank_name }} · {{ $receivingAccount->bank_branch }}</span>
                                    @else
                                        <span>{{ $receivingAccount->coin_symbol }} · {{ $receivingAccount->network }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="pa-destination-actions">
                                @if ($receivingAccount->qr_path)
                                    <a class="secondary-btn" href="{{ route('admin.payments.accounts.receiving.qr', $receivingAccount) }}">View QR</a>
                                @endif
                            </div>
                        </div>

                        <form class="pa-edit-form" method="POST" action="{{ route('admin.payments.accounts.receiving.update', $receivingAccount) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="type" value="{{ $receivingAccount->type }}">

                            <div class="pa-form-grid">
                                <div class="form-group">
                                    <label>Display Name</label>
                                    <input name="display_name" value="{{ $receivingAccount->display_name }}" required maxlength="120">
                                </div>

                                <div class="form-group">
                                    <label>Country Code</label>
                                    <input name="country_code" value="{{ $receivingAccount->country_code }}" required maxlength="2">
                                </div>

                                <div class="form-group">
                                    <label>Currency</label>
                                    <input name="currency" value="{{ $receivingAccount->currency }}" required maxlength="12">
                                </div>

                                <div class="form-group">
                                    <label>Amount Decimal Scale</label>
                                    <input type="number" name="amount_scale" value="{{ $scale }}" min="0" max="18" required>
                                </div>

                                <div class="form-group">
                                    <label>Exact Customer Payment Amount</label>
                                    <input name="tender_amount" value="{{ $displayAmount }}" inputmode="decimal" required maxlength="80">
                                </div>

                                @if ($receivingAccount->isBank())
                                    <div class="form-group">
                                        <label>Bank Name</label>
                                        <input name="bank_name" value="{{ $receivingAccount->bank_name }}" required maxlength="120">
                                    </div>

                                    <div class="form-group">
                                        <label>Branch</label>
                                        <input name="bank_branch" value="{{ $receivingAccount->bank_branch }}" required maxlength="120">
                                    </div>

                                    <div class="form-group">
                                        <label>Account Holder</label>
                                        <input name="account_holder" value="{{ $receivingAccount->account_holder }}" required maxlength="160">
                                    </div>

                                    <div class="form-group">
                                        <label>Account Number</label>
                                        <input name="account_number" value="{{ $receivingAccount->account_number }}" required maxlength="191" autocomplete="off">
                                    </div>
                                @else
                                    <div class="form-group">
                                        <label>Coin Name</label>
                                        <input name="coin_name" value="{{ $receivingAccount->coin_name }}" required maxlength="80">
                                    </div>

                                    <div class="form-group">
                                        <label>Coin Symbol</label>
                                        <input name="coin_symbol" value="{{ $receivingAccount->coin_symbol }}" required maxlength="20">
                                    </div>

                                    <div class="form-group">
                                        <label>Network</label>
                                        <input name="network" value="{{ $receivingAccount->network }}" required maxlength="60">
                                    </div>

                                    <div class="form-group span-2">
                                        <label>Wallet Address</label>
                                        <input name="wallet_address" value="{{ $receivingAccount->wallet_address }}" required maxlength="255" autocomplete="off">
                                    </div>

                                    <div class="form-group">
                                        <label>Replace QR Image</label>
                                        <input type="file" name="qr_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                                    </div>

                                    @if ($receivingAccount->qr_path)
                                        <div class="form-group">
                                            <label>Current QR</label>
                                            <input type="hidden" name="remove_qr" value="0">
                                            <div class="pa-toggle-row">
                                                <div class="pa-toggle-copy">
                                                    <strong>Remove current QR</strong>
                                                    <span>Only remove it when replacing or retiring this QR.</span>
                                                </div>
                                                <label class="pa-switch" aria-label="Remove current QR image">
                                                    <input type="checkbox" name="remove_qr" value="1">
                                                    <span class="pa-switch-track"></span>
                                                </label>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                <div class="form-group span-2">
                                    <label>Customer Instructions</label>
                                    <textarea name="instructions" maxlength="2000">{{ $receivingAccount->instructions }}</textarea>
                                </div>

                                <div class="form-group">
                                    <label>Display Order</label>
                                    <input type="number" name="display_order" value="{{ $receivingAccount->display_order }}" min="0" max="10000" required>
                                </div>
                            </div>

                            <input type="hidden" name="is_active" value="0">
                            <div class="pa-toggle-row">
                                <div class="pa-toggle-copy">
                                    <strong>Customer checkout availability</strong>
                                    <span>Deactivate instead of deleting a destination that has payment history.</span>
                                </div>
                                <label class="pa-switch" aria-label="Toggle receiving account availability">
                                    <input type="checkbox" name="is_active" value="1" {{ $receivingAccount->is_active ? 'checked' : '' }}>
                                    <span class="pa-switch-track"></span>
                                </label>
                            </div>

                            <div class="pa-form-actions">
                                <span class="pa-security-note">Historical prepared orders retain their original receiving snapshot.</span>
                                <button class="primary-btn" type="submit">Save Changes</button>
                            </div>
                        </form>
                    </article>
                @endforeach
            </div>
        @else
            <div class="pa-empty-state">
                No database-managed receiving accounts yet. Add your first bank account or wallet above.
            </div>
        @endif
    </section>
</div>
@endsection
