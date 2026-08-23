<?php

namespace App\Http\Requests\Api\Affiliate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AffiliateShareActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('channel')) {
            $this->merge([
                'channel' => strtoupper(
                    trim(
                        (string) $this->input(
                            'channel'
                        )
                    )
                ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'channel' => [
                'required',
                'string',
                Rule::in([
                    'WHATSAPP',
                    'TELEGRAM',
                ]),
            ],
        ];
    }
}
