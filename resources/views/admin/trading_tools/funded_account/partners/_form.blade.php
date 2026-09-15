@php
    $editing = isset($partner);
    $value = static function (string $field, mixed $default = '') use ($editing, $partner ?? null) {
        if (old($field) !== null) {
            return old($field);
        }

        return $editing ? ($partner->{$field} ?? $default) : $default;
    };
    $listValue = static function (string $field) use ($editing, $partner ?? null) {
        if (old($field) !== null) {
            return old($field);
        }

        if (! $editing) {
            return '';
        }

        $items = $partner->{$field};
        return is_array($items) ? implode(', ', $items) : '';
    };
@endphp

<div class="funded-advisor-grid">
    <label class="funded-advisor-field">
        <span>Partner Name</span>
        <input name="name" value="{{ $value('name') }}" required maxlength="160">
    </label>
    <label class="funded-advisor-field">
        <span>Display Priority</span>
        <input type="number" name="priority" min="0" max="100000" value="{{ $value('priority', 100) }}" required>
    </label>
    <label class="funded-advisor-field funded-advisor-field-full">
        <span>Official Website</span>
        <input type="url" name="website_url" value="{{ $value('website_url') }}" maxlength="2048" placeholder="https://...">
    </label>
    <label class="funded-advisor-field funded-advisor-field-full">
        <span>TradeX Affiliate / Referral URL</span>
        <input type="url" name="affiliate_url" value="{{ $value('affiliate_url') }}" maxlength="2048" placeholder="https://...">
        <small>This URL is snapshotted into each generated customer report and PDF.</small>
    </label>
    <label class="funded-advisor-field">
        <span>Promo Code</span>
        <input name="promo_code" value="{{ $value('promo_code') }}" maxlength="100">
    </label>
    <label class="funded-advisor-field">
        <span>Offer Text</span>
        <input name="offer_text" value="{{ $value('offer_text') }}" maxlength="255" placeholder="e.g. TradeX partner offer">
    </label>
    <label class="funded-advisor-field funded-advisor-field-full">
        <span>Affiliate Disclosure <small>Optional override</small></span>
        <textarea name="disclosure" rows="3" maxlength="2000" placeholder="Leave blank to use the TradeX standard disclosure.">{{ $value('disclosure') }}</textarea>
    </label>
    <label class="funded-advisor-field">
        <span>Supported Countries</span>
        <textarea name="supported_countries" rows="3" placeholder="LK, IN, AE">{{ $listValue('supported_countries') }}</textarea>
        <small>Comma or line separated.</small>
    </label>
    <label class="funded-advisor-field">
        <span>Account Sizes</span>
        <textarea name="account_sizes" rows="3" placeholder="5000, 10000, 25000, 50000, 100000">{{ $listValue('account_sizes') }}</textarea>
    </label>
    <label class="funded-advisor-field funded-advisor-field-full">
        <span>Challenge Types</span>
        <textarea name="challenge_types" rows="3" placeholder="1-Step, 2-Step, Instant Funding">{{ $listValue('challenge_types') }}</textarea>
    </label>
    <label class="funded-advisor-field funded-advisor-field-full">
        <span>Partner Logo <small>JPG / PNG / WebP, max 2 MB</small></span>
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
    </label>
</div>

<div class="funded-advisor-checks">
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $partner->is_active : true))> Active</label>
    <label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $editing ? $partner->is_featured : false))> Recommended / Featured</label>
    @if($editing && $partner->hasLogo())
        <label><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
    @endif
</div>

<div class="funded-advisor-callout">
    <strong>Independent recommendation policy</strong>
    <p>Partner commission never changes Funded Account Advisor risk calculations. Partner information is displayed as a separate referral block.</p>
</div>
