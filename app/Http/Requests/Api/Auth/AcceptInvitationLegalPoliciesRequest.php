<?php

namespace App\Http\Requests\Api\Auth;

class AcceptInvitationLegalPoliciesRequest extends RegistrationSessionRequest
{
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'acceptances' => [
                    'required',
                    'array',
                    'min:1',
                    'max:20',
                ],
                'acceptances.*.policy_uuid' => [
                    'required',
                    'uuid',
                    'distinct',
                ],
                'acceptances.*.content_sha256' => [
                    'required',
                    'string',
                    'size:64',
                    'regex:/\A[a-fA-F0-9]{64}\z/',
                ],
                'acceptances.*.accepted' => [
                    'required',
                    'accepted',
                ],
            ]
        );
    }
}
