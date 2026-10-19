<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class QuickUnlockVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'uuid'],
            'credential_id' => ['required', 'uuid'],
            'installation_id' => ['required', 'uuid'],
            'nonce' => ['required', 'string', 'min:32', 'max:128'],
            'signature' => ['required', 'string', 'min:32', 'max:2048'],
        ];
    }
}
