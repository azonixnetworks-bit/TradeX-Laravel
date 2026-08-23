<?php

namespace App\Http\Requests\Api\Education;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCoursesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language_code' => [
                'nullable',
                'string',
                Rule::in(
                    config(
                        'tradex_registration.allowed_languages',
                        ['en']
                    )
                ),
            ],

            'access_level' => [
                'nullable',
                'string',
                Rule::in([
                    'FREE',
                    'PREMIUM',
                ]),
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
                    .(int) config(
                        'tradex_education.courses_max_per_page',
                        50
                    ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->filled('language_code')) {
            $payload['language_code'] =
                strtolower(
                    trim(
                        (string)
                            $this->input(
                                'language_code'
                            )
                    )
                );
        }

        if ($this->filled('access_level')) {
            $payload['access_level'] =
                strtoupper(
                    trim(
                        (string)
                            $this->input(
                                'access_level'
                            )
                    )
                );
        }

        if ($payload !== []) {
            $this->merge(
                $payload
            );
        }
    }
}
