<?php

namespace App\Http\Requests\Api\Push;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('token'))) {
            $this->merge([
                'token' => trim((string) $this->input('token')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
                'min:16',
                'max:4096',
            ],
        ];
    }
}
