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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'outcome' => strtoupper(trim((string) $this->input('outcome'))),
        ]);
    }
}
