<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ActivateCustomerInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invitation_id' => [
                'required',
                'uuid',
            ],
            'token' => [
                'required',
                'string',
                'size:64',
                'regex:/\A[a-fA-F0-9]{64}\z/',
            ],
        ];
    }
}
