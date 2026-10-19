<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class QuickUnlockChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credential_id' => ['required', 'uuid'],
            'installation_id' => ['required', 'uuid'],
        ];
    }
}
