<?php

namespace App\Http\Requests\Api\OurChannel;

use Illuminate\Foundation\Http\FormRequest;

class ChannelVideoIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->has('language_code')
            && is_string(
                $this->input('language_code')
            )
        ) {
            $this->merge([
                'language_code' => strtolower(
                    trim(
                        (string) $this->input(
                            'language_code'
                        )
                    )
                ),
            ]);
        }

        if ($this->has('featured')) {
            $featured = filter_var(
                $this->input('featured'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($featured !== null) {
                $this->merge([
                    'featured' => $featured,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'language_code' => [
                'nullable',
                'string',
                'min:2',
                'max:10',
                'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?$/',
            ],
            'featured' => [
                'nullable',
                'boolean',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:'
                    .max(
                        1,
                        (int) config(
                            'tradex_our_channel_api.max_per_page',
                            50
                        )
                    ),
            ],
        ];
    }
}
