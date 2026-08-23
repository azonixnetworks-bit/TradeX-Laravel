<?php

namespace App\Http\Requests\Api\TradeIdeas;

use App\Models\TradeIdea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TradeIdeaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'category',
            'market_region',
            'direction',
            'status',
            'symbol',
        ] as $field) {
            if (
                $this->has($field)
                && is_string($this->input($field))
            ) {
                $normalized[$field] =
                    Str::upper(
                        trim(
                            (string) $this->input($field)
                        )
                    );
            }
        }

        if (
            $this->has('timeframe')
            && is_string($this->input('timeframe'))
        ) {
            $normalized['timeframe'] =
                Str::upper(
                    trim(
                        (string) $this->input('timeframe')
                    )
                );
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'category' => [
                'nullable',
                'string',
                Rule::in([
                    TradeIdea::CATEGORY_FOREX,
                    TradeIdea::CATEGORY_CRYPTO,
                    TradeIdea::CATEGORY_DERIV,
                    TradeIdea::CATEGORY_STOCKS,
                ]),
            ],

            'market_region' => [
                'nullable',
                'string',
                Rule::in([
                    TradeIdea::REGION_SRI_LANKA,
                    TradeIdea::REGION_INDIA,
                    TradeIdea::REGION_INTERNATIONAL,
                ]),
            ],

            'direction' => [
                'nullable',
                'string',
                Rule::in([
                    TradeIdea::DIRECTION_BUY,
                    TradeIdea::DIRECTION_SELL,
                ]),
            ],

            'timeframe' => [
                'nullable',
                'string',
                Rule::in(
                    TradeIdea::TIMEFRAMES
                ),
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    TradeIdea::STATUS_PUBLISHED,
                    TradeIdea::STATUS_CLOSED,
                ]),
            ],

            'symbol' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Z0-9._:\/-]+$/',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:'
                    .max(
                        1,
                        (int) config(
                            'tradex_trade_ideas_api.max_per_page',
                            50
                        )
                    ),
            ],
        ];
    }
}
