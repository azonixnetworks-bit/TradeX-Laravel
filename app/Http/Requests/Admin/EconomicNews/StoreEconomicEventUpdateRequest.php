<?php

namespace App\Http\Requests\Admin\EconomicNews;

use App\Models\AdminUser;
use App\Models\EconomicEvent;
use App\Models\EconomicEventUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEconomicEventUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && ($admin->isSuperAdmin() || $admin->hasPermission('economic_news.live_updates.manage'));
    }

    public function rules(): array
    {
        return [
            'update_type' => ['required', Rule::in([
                EconomicEventUpdate::TYPE_PRE_EVENT,
                EconomicEventUpdate::TYPE_LIVE,
                EconomicEventUpdate::TYPE_RELEASE,
                EconomicEventUpdate::TYPE_REVISION,
                EconomicEventUpdate::TYPE_ANALYSIS,
            ])],
            'message' => ['required', 'string', 'min:3', 'max:10000'],
            'actual' => ['nullable', 'string', 'max:120'],
            'forecast' => ['nullable', 'string', 'max:120'],
            'previous' => ['nullable', 'string', 'max:120'],
            'bias' => ['nullable', Rule::in([
                EconomicEvent::BIAS_BULLISH,
                EconomicEvent::BIAS_BEARISH,
                EconomicEvent::BIAS_NEUTRAL,
            ])],
            'confidence_score' => ['nullable', 'integer', 'between:0,100'],
            'volatility' => ['nullable', Rule::in([
                EconomicEvent::VOLATILITY_LOW,
                EconomicEvent::VOLATILITY_MEDIUM,
                EconomicEvent::VOLATILITY_HIGH,
                EconomicEvent::VOLATILITY_EXTREME,
            ])],
            'is_published' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['update_type', 'bias', 'volatility'] as $field) {
            $this->merge([
                $field => filled($this->input($field))
                    ? strtoupper(trim((string) $this->input($field)))
                    : null,
            ]);
        }

        $this->merge([
            'message' => trim((string) $this->input('message')),
            'is_published' => filter_var($this->input('is_published', false), FILTER_VALIDATE_BOOL),
        ]);
    }
}
