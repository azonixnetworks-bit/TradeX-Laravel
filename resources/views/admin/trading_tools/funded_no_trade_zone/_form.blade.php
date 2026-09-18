@php
    $editing = isset($rule);
    $ruleModel = $editing ? $rule : null;

    $value = static function (string $field, mixed $default = '') use ($editing, $ruleModel) {
        if (old($field) !== null) {
            return old($field);
        }

        return $editing ? ($ruleModel->{$field} ?? $default) : $default;
    };

    $listValue = static function (string $field) use ($editing, $ruleModel) {
        if (old($field) !== null) {
            return old($field);
        }

        if (! $editing) {
            return '';
        }

        $items = $ruleModel->{$field};
        return is_array($items) ? implode(', ', $items) : '';
    };

    $selectedImpacts = (array) old('impact_levels', $editing ? $ruleModel->impact_levels : ['HIGH']);
@endphp

<div class="funded-advisor-grid">
    <label class="funded-advisor-field">
        <span>Funded Account Company</span>
        <select name="funded_account_partner_id" required>
            <option value="">Select company</option>
            @foreach($partners as $partner)
                <option value="{{ $partner->id }}" @selected((string) $value('funded_account_partner_id') === (string) $partner->id)>
                    {{ $partner->name }}{{ $partner->is_active ? '' : ' (Inactive)' }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="funded-advisor-field">
        <span>Program / Account Type</span>
        <input name="program_name" value="{{ $value('program_name') }}" maxlength="160" placeholder="Blank = all programs">
        <small>Use the exact option exposed to the customer, for example Standard, High Stakes or Stellar 2-Step.</small>
    </label>

    <label class="funded-advisor-field">
        <span>Phase</span>
        <select name="phase" required>
            @foreach(['ALL' => 'All Phases', 'CHALLENGE' => 'Challenge', 'VERIFICATION' => 'Verification', 'FUNDED' => 'Funded'] as $phaseValue => $phaseLabel)
                <option value="{{ $phaseValue }}" @selected($value('phase', 'ALL') === $phaseValue)>{{ $phaseLabel }}</option>
            @endforeach
        </select>
    </label>

    <label class="funded-advisor-field">
        <span>News Trading Policy</span>
        <select name="policy" required>
            @foreach(['RESTRICTED' => 'Restricted / No Trade Zone', 'CONDITIONAL' => 'Conditional Rule / Warning Only', 'ALLOWED' => 'Allowed'] as $policyValue => $policyLabel)
                <option value="{{ $policyValue }}" @selected($value('policy', 'RESTRICTED') === $policyValue)>{{ $policyLabel }}</option>
            @endforeach
        </select>
        <small>Only RESTRICTED records generate customer No Trade Zones. CONDITIONAL records remain compliance intelligence.</small>
    </label>

    <label class="funded-advisor-field">
        <span>Enforcement / Consequence</span>
        <select name="enforcement_type" required>
            @foreach([
                'BLOCKED' => 'Trading Blocked',
                'HARD_BREACH' => 'Hard Breach / Account Risk',
                'SOFT_BREACH' => 'Soft Breach',
                'PROFIT_DEDUCTION' => 'Profit Deduction',
                'REWARD_SHARE' => 'Reduced Reward Share',
                'GUIDANCE' => 'Guidance / Special Condition',
            ] as $enforcementValue => $enforcementLabel)
                <option value="{{ $enforcementValue }}" @selected($value('enforcement_type', 'BLOCKED') === $enforcementValue)>{{ $enforcementLabel }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="funded-advisor-section-title">Restricted News Matching</div>
<div class="funded-advisor-checks">
    @foreach(['HIGH', 'MEDIUM', 'LOW'] as $impact)
        <label><input type="checkbox" name="impact_levels[]" value="{{ $impact }}" @checked(in_array($impact, $selectedImpacts, true))> {{ $impact }}</label>
    @endforeach
</div>

<div class="funded-advisor-grid" style="margin-top:16px">
    <label class="funded-advisor-field funded-advisor-field-full">
        <span>Event Name Keywords</span>
        <textarea name="event_keywords" rows="3" placeholder="CPI, NON-FARM, FEDERAL FUNDS RATE">{{ $listValue('event_keywords') }}</textarea>
        <small>Optional comma/new-line list. When present, the economic-event title must contain at least one keyword. This prevents broad HIGH-impact rules from blocking unrelated news.</small>
    </label>

    <label class="funded-advisor-field">
        <span>Minutes Before News</span>
        <input type="number" name="minutes_before" min="0" max="180" value="{{ $value('minutes_before', 2) }}" required>
    </label>

    <label class="funded-advisor-field">
        <span>Minutes After News</span>
        <input type="number" name="minutes_after" min="0" max="180" value="{{ $value('minutes_after', 2) }}" required>
    </label>

    <label class="funded-advisor-field">
        <span>Restricted Currencies</span>
        <textarea name="restricted_currencies" rows="3" placeholder="USD, EUR, GBP">{{ $listValue('restricted_currencies') }}</textarea>
        <small>Optional. Blank means every event currency may match.</small>
    </label>

    <label class="funded-advisor-field">
        <span>Restricted Instruments</span>
        <textarea name="restricted_instruments" rows="3" placeholder="EURUSD, GBPUSD, XAUUSD">{{ $listValue('restricted_instruments') }}</textarea>
        <small>Optional. With affected-only enabled, TradeX intersects this list with event pair mapping.</small>
    </label>

    <label class="funded-advisor-field funded-advisor-field-full">
        <span>Rule Summary</span>
        <textarea name="rule_summary" rows="4" maxlength="5000" placeholder="Describe the official rule and any exception TradeX cannot automate.">{{ $value('rule_summary') }}</textarea>
    </label>

    <label class="funded-advisor-field funded-advisor-field-full">
        <span>Official Rule Source URL</span>
        <input type="url" name="source_url" value="{{ $value('source_url') }}" maxlength="2048" placeholder="https://company.example/rules">
    </label>

    <label class="funded-advisor-field">
        <span>Source Reference</span>
        <input name="source_reference" value="{{ $value('source_reference') }}" maxlength="255" placeholder="News Trading Policy / FAQ section">
    </label>

    <label class="funded-advisor-field">
        <span>Last Verified</span>
        <input type="datetime-local" name="verified_at" value="{{ old('verified_at', $editing && $ruleModel->verified_at ? $ruleModel->verified_at->format('Y-m-d\TH:i') : '') }}">
    </label>

    <label class="funded-advisor-field">
        <span>Effective From</span>
        <input type="datetime-local" name="effective_from" value="{{ old('effective_from', $editing && $ruleModel->effective_from ? $ruleModel->effective_from->format('Y-m-d\TH:i') : '') }}">
    </label>

    <label class="funded-advisor-field">
        <span>Expires At</span>
        <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $editing && $ruleModel->expires_at ? $ruleModel->expires_at->format('Y-m-d\TH:i') : '') }}">
    </label>

    <label class="funded-advisor-field">
        <span>Rule Priority</span>
        <input type="number" name="priority" min="0" max="100000" value="{{ $value('priority', 100) }}" required>
        <small>Lower number wins when multiple rules match the same event.</small>
    </label>
</div>

<div class="funded-advisor-section-title">Trade Actions</div>
<div class="funded-advisor-checks">
    <input type="hidden" name="affected_instruments_only" value="0">
    <label><input type="checkbox" name="affected_instruments_only" value="1" @checked(old('affected_instruments_only', $editing ? $ruleModel->affected_instruments_only : true))> Affected instruments only</label>

    <input type="hidden" name="block_open_new_trades" value="0">
    <label><input type="checkbox" name="block_open_new_trades" value="1" @checked(old('block_open_new_trades', $editing ? $ruleModel->block_open_new_trades : true))> Block new trades</label>

    <input type="hidden" name="block_close_trades" value="0">
    <label><input type="checkbox" name="block_close_trades" value="1" @checked(old('block_close_trades', $editing ? $ruleModel->block_close_trades : false))> Block closing trades</label>

    <input type="hidden" name="block_pending_orders" value="0">
    <label><input type="checkbox" name="block_pending_orders" value="1" @checked(old('block_pending_orders', $editing ? $ruleModel->block_pending_orders : true))> Block pending-entry execution</label>

    <input type="hidden" name="is_active" value="0">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $ruleModel->is_active : true))> Active rule</label>
</div>

<div class="funded-advisor-callout">
    <strong>Rule accuracy</strong>
    <p>Store only rules verified from the funded company's official terms or support documentation. TradeX keeps conditional profit-share or strategy rules separate so they are not incorrectly presented as a mandatory No Trade Zone.</p>
</div>
