<?php

namespace App\Http\Requests\Api\TradeIdeas;

use App\Models\TradeIdeaDistribution;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TradeIdeaShareActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (
            $this->has('channel')
            && is_string($this->input('channel'))
        ) {
            $normalized['channel'] =
                Str::upper(
                    trim(
                        (string) $this->input('channel')
                    )
                );
        }

        if (
            $this->has('variant')
            && is_string($this->input('variant'))
        ) {
            $normalized['variant'] =
                Str::lower(
                    trim(
                        (string) $this->input('variant')
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
            'channel' => [
                'required',
                'string',
                Rule::in([
                    TradeIdeaDistribution::CHANNEL_WHATSAPP,
                    TradeIdeaDistribution::CHANNEL_TELEGRAM,
                ]),
            ],

            'variant' => [
                'nullable',
                'string',
                Rule::in([
                    TradeIdeaShareCardService::VARIANT_SQUARE,
                    TradeIdeaShareCardService::VARIANT_PORTRAIT,
                    TradeIdeaShareCardService::VARIANT_STORY,
                ]),
            ],
        ];
    }
}
