<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegistrationSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_id' => [
                'required',
                'uuid',
            ],

            'registration_token' => [
                'required',
                'string',
                'size:64',
            ],
        ];
    }
}
