<?php

namespace App\Http\Requests\Admin\TradeIdeas;

use App\Models\AdminUser;
use App\Models\TradeIdea;
use App\Services\TradeIdeas\TradeIdeaStructureValidator;
use App\Support\TradingViewUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class TradeIdeaPayloadRequest extends FormRequest
{
    abstract protected function requiredPermission(): string;

    public function authorize(): bool
    {
        $admin = auth(
            'admin'
        )->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                $this->requiredPermission()
            );
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:180',
            ],

            'summary' => [
                'nullable',
                'string',
                'max:500',
            ],

            'analysis' => [
                'nullable',
                'string',
                'max:30000',
            ],

            'tradingview_url' => [
                'nullable',
                'string',
                'max:2048',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if (
                        $value !== null
                        && trim((string) $value) !== ''
                        && TradingViewUrl::normalize(
                            (string) $value
                        ) === null
                    ) {
                        $fail(
                            'Enter a valid HTTPS TradingView chart/share URL.'
                        );
                    }
                },
            ],

            'chart_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'remove_chart_image' => [
                'sometimes',
                'boolean',
            ],

            'category' => [
                'required',
                Rule::in([
                    TradeIdea::CATEGORY_FOREX,
                    TradeIdea::CATEGORY_CRYPTO,
                    TradeIdea::CATEGORY_DERIV,
                    TradeIdea::CATEGORY_STOCKS,
                ]),
            ],

            'market_region' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('category')
                        === TradeIdea::CATEGORY_STOCKS
                ),
                Rule::prohibitedIf(
                    fn (): bool => $this->input('category')
                        !== TradeIdea::CATEGORY_STOCKS
                ),
                'nullable',
                Rule::in([
                    TradeIdea::REGION_SRI_LANKA,
                    TradeIdea::REGION_INDIA,
                    TradeIdea::REGION_INTERNATIONAL,
                ]),
            ],

            'symbol' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9._\-\/]{1,40}$/',
            ],

            'instrument_name' => [
                'nullable',
                'string',
                'max:120',
            ],

            'direction' => [
                'required',
                Rule::in([
                    TradeIdea::DIRECTION_BUY,
                    TradeIdea::DIRECTION_SELL,
                ]),
            ],

            'timeframe' => [
                'required',
                Rule::in(
                    TradeIdea::TIMEFRAMES
                ),
            ],

            // ADMIN-05 REV3: every Trade Structure field is optional.
            'entry_price_low' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'entry_price_high' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'stop_loss' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'targets' => [
                'nullable',
                'array',
                'max:5',
            ],

            'targets.*.price' => [
                'required',
                'numeric',
                'gt:0',
            ],

            // Removed from ADMIN-05 UI/contract.
            'language_code' => [
                'prohibited',
            ],

            'valid_until' => [
                'prohibited',
            ],

            'uuid' => [
                'prohibited',
            ],

            'status' => [
                'prohibited',
            ],

            'published_at' => [
                'prohibited',
            ],

            'closed_at' => [
                'prohibited',
            ],

            'close_outcome' => [
                'prohibited',
            ],

            'close_price' => [
                'prohibited',
            ],

            'close_note' => [
                'prohibited',
            ],

            'current_stop_loss' => [
                'prohibited',
            ],

            'entry_triggered_at' => [
                'prohibited',
            ],

            'entry_price_actual' => [
                'prohibited',
            ],

            'chart_image_disk' => [
                'prohibited',
            ],

            'chart_image_path' => [
                'prohibited',
            ],

            'chart_image_mime_type' => [
                'prohibited',
            ],

            'chart_image_size_bytes' => [
                'prohibited',
            ],

            'created_by_admin_id' => [
                'prohibited',
            ],

            'updated_by_admin_id' => [
                'prohibited',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $errors = app(
                    TradeIdeaStructureValidator::class
                )->errors(
                    (string) $this->input('direction'),
                    $this->input('entry_price_low'),
                    $this->input('entry_price_high'),
                    $this->input('stop_loss'),
                    (array) $this->input('targets', [])
                );

                foreach ($errors as $field => $message) {
                    $validator->errors()->add(
                        $field,
                        $message
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => strtoupper(
                trim(
                    (string) $this->input('category')
                )
            ),

            'market_region' => $this->filled('market_region')
                ? strtoupper(
                    trim(
                        (string) $this->input('market_region')
                    )
                )
                : null,

            'symbol' => strtoupper(
                trim(
                    (string) $this->input('symbol')
                )
            ),

            'direction' => strtoupper(
                trim(
                    (string) $this->input('direction')
                )
            ),

            'timeframe' => strtoupper(
                trim(
                    (string) $this->input('timeframe')
                )
            ),

            'tradingview_url' => $this->filled('tradingview_url')
                ? trim(
                    (string) $this->input('tradingview_url')
                )
                : null,
        ]);
    }
}
