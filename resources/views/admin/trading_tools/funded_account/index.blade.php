@extends('admin.layouts.app')

@section('title', 'Funded Account Advisor | TradeX Administration')
@section('page-title', 'Funded Account Advisor')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">TRADING TOOLS</div>
            <h1>Funded Account <span>Advisor</span></h1>
            <p>Build a conservative, firm-rule-aware risk plan with clear entry limits, drawdown capacity and a downloadable customer PDF.</p>
        </div>

        @if(auth('admin')->user()?->hasPermission('funded_advisor.partners.manage'))
            <a class="secondary-btn" href="{{ route('admin.funded_advisor.partners.index') }}">Manage Funded Partners</a>
        @endif
    </div>

    @if($errors->any())
        <section class="panel funded-advisor-alert is-danger" role="alert">
            <strong>Please correct the highlighted advisor inputs.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="funded-advisor-layout">
        <section class="panel funded-advisor-form-panel">
            <div class="funded-advisor-panel-heading">
                <div>
                    <div class="panel-eyebrow">CUSTOMER INPUT</div>
                    <h2>Account & Firm Rules</h2>
                </div>
                <span>01</span>
            </div>

            <form method="POST" action="{{ route('admin.funded_advisor.reports.generate') }}" id="fundedAdvisorForm">
                @csrf

                <div class="funded-advisor-section-title">Customer</div>
                <div class="funded-advisor-grid">
                    <label class="funded-advisor-field">
                        <span>Customer Name</span>
                        <input name="customer_name" value="{{ old('customer_name') }}" required maxlength="160">
                    </label>
                    <label class="funded-advisor-field">
                        <span>Customer Email <small>Optional</small></span>
                        <input type="email" name="customer_email" value="{{ old('customer_email') }}" maxlength="255">
                    </label>
                    <label class="funded-advisor-field">
                        <span>Prop Firm Name <small>Manual label</small></span>
                        <input name="prop_firm_name" value="{{ old('prop_firm_name') }}" maxlength="160" placeholder="e.g. Selected funded provider">
                    </label>
                    <label class="funded-advisor-field">
                        <span>TradeX Partner <small>Optional affiliate block</small></span>
                        <select name="partner_uuid">
                            <option value="">No partner attached</option>
                            @foreach($partners as $partner)
                                <option value="{{ $partner->uuid }}" @selected(old('partner_uuid') === $partner->uuid)>
                                    {{ $partner->name }}{{ $partner->is_featured ? ' · Recommended' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="funded-advisor-section-title">Challenge Rules</div>
                <div class="funded-advisor-grid">
                    <label class="funded-advisor-field">
                        <span>Phase</span>
                        <select name="phase" required>
                            @foreach(['EVALUATION' => 'Evaluation', 'VERIFICATION' => 'Verification', 'FUNDED' => 'Funded'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('phase', 'EVALUATION') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Account Size</span>
                        <div class="funded-advisor-money"><b>$</b><input type="number" step="0.01" min="0.01" name="account_size" value="{{ old('account_size', '100000') }}" required></div>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Profit Target %</span>
                        <input type="number" step="0.01" min="0" max="100" name="profit_target_percent" value="{{ old('profit_target_percent', '10') }}" required>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Daily Loss Limit %</span>
                        <input type="number" step="0.01" min="0.01" max="100" name="daily_loss_limit_percent" value="{{ old('daily_loss_limit_percent', '5') }}" required>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Maximum Drawdown %</span>
                        <input type="number" step="0.01" min="0.01" max="100" name="max_drawdown_percent" value="{{ old('max_drawdown_percent', '10') }}" required>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Daily Loss Basis</span>
                        <select name="daily_loss_basis" required>
                            <option value="EQUITY" @selected(old('daily_loss_basis', 'EQUITY') === 'EQUITY')>Equity</option>
                            <option value="BALANCE" @selected(old('daily_loss_basis') === 'BALANCE')>Balance</option>
                        </select>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Daily Start Reference</span>
                        <div class="funded-advisor-money"><b>$</b><input type="number" step="0.01" min="0.01" name="daily_start_reference" value="{{ old('daily_start_reference', '100000') }}" required></div>
                        <small>Use the provider's correct balance/equity reference for the current daily reset.</small>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Drawdown Type</span>
                        <select name="drawdown_type" id="fundedDrawdownType" required>
                            <option value="STATIC" @selected(old('drawdown_type', 'STATIC') === 'STATIC')>Static</option>
                            <option value="TRAILING" @selected(old('drawdown_type') === 'TRAILING')>Trailing</option>
                        </select>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Drawdown Basis</span>
                        <select name="drawdown_basis" required>
                            <option value="EQUITY" @selected(old('drawdown_basis', 'EQUITY') === 'EQUITY')>Equity</option>
                            <option value="BALANCE" @selected(old('drawdown_basis') === 'BALANCE')>Balance</option>
                        </select>
                    </label>
                    <label class="funded-advisor-field" id="fundedTrailingReferenceField">
                        <span>Trailing High-Water Reference</span>
                        <div class="funded-advisor-money"><b>$</b><input type="number" step="0.01" min="0.01" name="trailing_reference_amount" value="{{ old('trailing_reference_amount') }}"></div>
                        <small>Required only for trailing drawdown. Enter the current provider-defined trailing reference.</small>
                    </label>
                </div>

                <div class="funded-advisor-section-title">Current Account State</div>
                <div class="funded-advisor-grid">
                    <label class="funded-advisor-field">
                        <span>Current Balance</span>
                        <div class="funded-advisor-money"><b>$</b><input type="number" step="0.01" min="0.01" name="current_balance" value="{{ old('current_balance', '100000') }}" required></div>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Current Equity</span>
                        <div class="funded-advisor-money"><b>$</b><input type="number" step="0.01" min="0.01" name="current_equity" value="{{ old('current_equity', '100000') }}" required></div>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Desired Risk / Trade %</span>
                        <input type="number" step="0.01" min="0.01" max="10" name="desired_risk_percent" value="{{ old('desired_risk_percent', '0.25') }}" required>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Maximum Planned Entries</span>
                        <input type="number" step="1" min="1" max="20" name="planned_max_entries" value="{{ old('planned_max_entries', '3') }}" required>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Minimum R:R</span>
                        <input type="number" step="0.1" min="0.1" max="20" name="minimum_rr" value="{{ old('minimum_rr', '2') }}" required>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Minimum Trading Days</span>
                        <input type="number" step="1" min="0" max="365" name="minimum_trading_days" value="{{ old('minimum_trading_days', '0') }}" required>
                    </label>
                    <label class="funded-advisor-field">
                        <span>Completed Trading Days</span>
                        <input type="number" step="1" min="0" max="3650" name="completed_trading_days" value="{{ old('completed_trading_days', '0') }}" required>
                    </label>
                </div>

                <div class="funded-advisor-callout">
                    <strong>Planning rule</strong>
                    <p>TradeX never increases risk after a loss. The advisor can reduce your requested risk to preserve daily and overall drawdown capacity.</p>
                </div>

                <button class="primary-btn funded-advisor-submit" type="submit">Generate Customer Risk Plan</button>
            </form>
        </section>

        <aside class="panel funded-advisor-side-panel">
            <div class="funded-advisor-panel-heading">
                <div>
                    <div class="panel-eyebrow">RECENT</div>
                    <h2>Advisor Reports</h2>
                </div>
                <span>02</span>
            </div>

            @forelse($recentReports as $report)
                <a class="funded-advisor-report-link" href="{{ route('admin.funded_advisor.reports.show', $report) }}">
                    <strong>{{ $report->customer_name }}</strong>
                    <span>{{ $report->prop_firm_name ?: 'Manual Firm Rules' }}</span>
                    <small>{{ $report->generated_at?->format('Y-m-d H:i') }}</small>
                </a>
            @empty
                <div class="funded-advisor-empty">No advisor reports generated yet.</div>
            @endforelse

            <div class="funded-advisor-side-note">
                <strong>Firm-neutral by design</strong>
                <p>Enter the customer's current official provider rules. TradeX does not hardcode a prop firm's changing challenge rules.</p>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/funded-account-advisor.js') }}"></script>
@endpush
