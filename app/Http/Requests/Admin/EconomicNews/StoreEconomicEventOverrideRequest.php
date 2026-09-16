<?php

namespace App\Http\Requests\Admin\EconomicNews;

use App\Models\AdminUser;
use App\Models\EconomicEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEconomicEventOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && ($admin->isSuperAdmin() || $admin->hasPermission('economic_news.overrides.manage'));
    }

    public function rules(): array
    {
        return [
            'impact' => ['nullable', Rule::in([
                EconomicEvent::IMPACT_HIGH,
                EconomicEvent::IMPACT_MEDIUM,
                EconomicEvent::IMPACT_LOW,
            ])],
            'status' => ['nullable', Rule::in([
                EconomicEvent::STATUS_SCHEDULED,
                EconomicEvent::STATUS_LIVE,
                EconomicEvent::STATUS_RELEASED,
                EconomicEvent::STATUS_REVISED,
                EconomicEvent::STATUS_CANCELLED,
            ])],
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
            'reason' => ['nullable', 'string', 'max:5000'],
            'analysis' => ['nullable', 'string', 'max:20000'],
            'override_note' => ['required', 'string', 'min:8', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $fields = [
                    'impact',
                    'status',
                    'bias',
                    'confidence_score',
                    'volatility',
                    'reason',
                    'analysis',
                ];

                $hasOverride = collect($fields)->contains(
                    fn (string $field): bool => $this->input($field) !== null && $this->input($field) !== ''
                );

                if (! $hasOverride) {
                    $validator->errors()->add('override_note', 'At least one intelligence field must be overridden.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['impact', 'status', 'bias', 'volatility'] as $field) {
            if (filled($this->input($field))) {
                $this->merge([$field => strtoupper(trim((string) $this->input($field)))]);
            } else {
                $this->merge([$field => null]);
            }
        }

        $this->merge([
            'reason' => filled($this->input('reason')) ? trim((string) $this->input('reason')) : null,
            'analysis' => filled($this->input('analysis')) ? trim((string) $this->input('analysis')) : null,
            'override_note' => trim((string) $this->input('override_note')),
            'expires_at' => filled($this->input('expires_at')) ? $this->input('expires_at') : null,
        ]);
    }
}
