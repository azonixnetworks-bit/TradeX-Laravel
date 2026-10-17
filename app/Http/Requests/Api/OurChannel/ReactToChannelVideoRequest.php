<?php

namespace App\Http\Requests\Api\OurChannel;

use App\Models\UserChannelVideoReaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReactToChannelVideoRequest extends FormRequest
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
                    UserChannelVideoReaction::LIKE,
                    UserChannelVideoReaction::DISLIKE,
                ]),
            ],
        ];
    }
}
