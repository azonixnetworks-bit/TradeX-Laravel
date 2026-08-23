<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfilePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language_code' => [
                'required',
                'string',
                Rule::in(
                    config(
                        'tradex_registration.allowed_languages',
                        ['en']
                    )
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'language_code' =>
                strtolower(
                    trim(
                        (string) $this->input(
                            'language_code'
                        )
                    )
                ),
        ]);
    }
}
