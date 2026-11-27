<?php

namespace App\Http\Requests\Admin\TradeIdeas;

use App\Models\AdminUser;
use App\Models\TradeIdea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseTradeIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('trade_ideas.close');
    }

    public function rules(): array
    {
        return [
            'outcome' => [
                'required',
                Rule::in([
                    TradeIdea::OUTCOME_WIN,
                    TradeIdea::OUTCOME_LOSS,
                    TradeIdea::OUTCOME_BREAKEVEN,
                    TradeIdea::OUTCOME_CANCELLED,
                    TradeIdea::OUTCOME_EXPIRED,
                ]),
            ],
            'close_price' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['required', 'string', 'max:5000'],
            'show_on_website' => ['nullable', 'boolean'],
            'after_chart_image' => [
                'nullable',
                'required_if:show_on_website,1',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'outcome' => strtoupper(trim((string) $this->input('outcome'))),
            'show_on_website' => $this->boolean('show_on_website'),
        ]);
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $this->boolean('show_on_website')) {
                return;
            }

            $tradeIdea = $this->route('tradeIdea');

            if (! $tradeIdea instanceof TradeIdea || ! $tradeIdea->hasChartImage()) {
                $validator->errors()->add(
                    'show_on_website',
                    'A Before chart image is required before publishing this result.'
                );
            }

            if (! in_array($this->input('outcome'), [
                TradeIdea::OUTCOME_WIN,
                TradeIdea::OUTCOME_LOSS,
                TradeIdea::OUTCOME_BREAKEVEN,
            ], true)) {
                $validator->errors()->add(
                    'show_on_website',
                    'Only WIN, LOSS or BREAKEVEN results can be shown on the website.'
                );
            }
        }];
    }
}
