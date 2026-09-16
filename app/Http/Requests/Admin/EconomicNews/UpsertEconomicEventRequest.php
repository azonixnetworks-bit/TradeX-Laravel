<?php

namespace App\Http\Requests\Admin\EconomicNews;

use App\Models\AdminUser;
use App\Models\EconomicEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertEconomicEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && ($admin->isSuperAdmin() || $admin->hasPermission('economic_news.events.manage'));
    }

    public function rules(): array
    {
        return [
            'source_uuid' => ['nullable', 'uuid', Rule::exists('economic_news_sources', 'uuid')],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'max:8', 'regex:/^[A-Z0-9_-]+$/'],
            'currency' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9]+$/'],
            'impact' => ['required', Rule::in([
                EconomicEvent::IMPACT_HIGH,
                EconomicEvent::IMPACT_MEDIUM,
                EconomicEvent::IMPACT_LOW,
            ])],
            'scheduled_at_utc' => ['required', 'date'],
            'status' => ['required', Rule::in([
                EconomicEvent::STATUS_SCHEDULED,
                EconomicEvent::STATUS_LIVE,
                EconomicEvent::STATUS_RELEASED,
                EconomicEvent::STATUS_REVISED,
                EconomicEvent::STATUS_CANCELLED,
            ])],
            'actual' => ['nullable', 'string', 'max:120'],
            'forecast' => ['nullable', 'string', 'max:120'],
            'previous' => ['nullable', 'string', 'max:120'],
            'revised_previous' => ['nullable', 'string', 'max:120'],
            'bias' => ['required', Rule::in([
                EconomicEvent::BIAS_BULLISH,
                EconomicEvent::BIAS_BEARISH,
                EconomicEvent::BIAS_NEUTRAL,
            ])],
            'confidence_score' => ['required', 'integer', 'between:0,100'],
            'volatility' => ['required', Rule::in([
                EconomicEvent::VOLATILITY_LOW,
                EconomicEvent::VOLATILITY_MEDIUM,
                EconomicEvent::VOLATILITY_HIGH,
                EconomicEvent::VOLATILITY_EXTREME,
            ])],
            'reason' => ['nullable', 'string', 'max:5000'],
            'analysis' => ['nullable', 'string', 'max:20000'],
            'is_published' => ['required', 'boolean'],
            'pairs' => ['array', 'max:20'],
            'pairs.*.market' => ['required', Rule::in(['FOREX', 'CRYPTO', 'DERIV', 'STOCKS'])],
            'pairs.*.symbol' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9._-]+$/'],
            'pairs.*.bias' => ['required', Rule::in([
                EconomicEvent::BIAS_BULLISH,
                EconomicEvent::BIAS_BEARISH,
                EconomicEvent::BIAS_NEUTRAL,
            ])],
            'pairs.*.confidence_score' => ['required', 'integer', 'between:0,100'],
            'pairs.*.volatility' => ['required', Rule::in([
                EconomicEvent::VOLATILITY_LOW,
                EconomicEvent::VOLATILITY_MEDIUM,
                EconomicEvent::VOLATILITY_HIGH,
                EconomicEvent::VOLATILITY_EXTREME,
            ])],
            'pairs.*.reason' => ['nullable', 'string', 'max:2000'],
            'pairs.*.is_primary' => ['required', 'boolean'],
            'pairs.*.sort_order' => ['required', 'integer', 'between:1,1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $pairs = array_values(array_filter(
            array_map(function (mixed $pair): array {
                $pair = is_array($pair) ? $pair : [];

                return [
                    'market' => strtoupper(trim((string) ($pair['market'] ?? 'FOREX'))),
                    'symbol' => strtoupper(trim((string) ($pair['symbol'] ?? ''))),
                    'bias' => strtoupper(trim((string) ($pair['bias'] ?? EconomicEvent::BIAS_NEUTRAL))),
                    'confidence_score' => $pair['confidence_score'] ?? 0,
                    'volatility' => strtoupper(trim((string) ($pair['volatility'] ?? EconomicEvent::VOLATILITY_MEDIUM))),
                    'reason' => filled($pair['reason'] ?? null) ? trim((string) $pair['reason']) : null,
                    'is_primary' => filter_var($pair['is_primary'] ?? false, FILTER_VALIDATE_BOOL),
                    'sort_order' => $pair['sort_order'] ?? 100,
                ];
            }, (array) $this->input('pairs', [])),
            static fn (array $pair): bool => $pair['symbol'] !== ''
        ));

        $this->merge([
            'source_uuid' => filled($this->input('source_uuid')) ? trim((string) $this->input('source_uuid')) : null,
            'title' => trim((string) $this->input('title')),
            'category' => filled($this->input('category')) ? trim((string) $this->input('category')) : null,
            'country_code' => filled($this->input('country_code')) ? strtoupper(trim((string) $this->input('country_code'))) : null,
            'currency' => filled($this->input('currency')) ? strtoupper(trim((string) $this->input('currency'))) : null,
            'impact' => strtoupper(trim((string) $this->input('impact'))),
            'status' => strtoupper(trim((string) $this->input('status'))),
            'bias' => strtoupper(trim((string) $this->input('bias'))),
            'volatility' => strtoupper(trim((string) $this->input('volatility'))),
            'is_published' => filter_var($this->input('is_published', false), FILTER_VALIDATE_BOOL),
            'pairs' => $pairs,
        ]);
    }
}
