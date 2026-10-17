<?php

namespace App\Http\Requests\Api\TradeIdeas;

use App\Models\UserTradeIdeaReaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReactToTradeIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reaction'))) {
            $this->merge([
                'reaction' => Str::upper(trim((string) $this->input('reaction'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reaction' => [
                'nullable',
                'string',
                Rule::in([
                    UserTradeIdeaReaction::LIKE,
                    UserTradeIdeaReaction::DISLIKE,
                ]),
            ],
        ];
    }
}
