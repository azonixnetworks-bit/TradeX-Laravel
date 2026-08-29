<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Validation\Rules\Password;

class SetInvitationPasswordRequest extends RegistrationSessionRequest
{
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)
                        ->mixedCase()
                        ->letters()
                        ->numbers()
                        ->symbols(),
                ],
            ]
        );
    }
}
