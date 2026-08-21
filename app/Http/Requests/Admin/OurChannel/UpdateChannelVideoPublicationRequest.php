<?php

namespace App\Http\Requests\Admin\OurChannel;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChannelVideoPublicationRequest extends FormRequest
{
    public const ACTION_PUBLISH = 'PUBLISH';

    public const ACTION_UNPUBLISH = 'UNPUBLISH';

    public function authorize(): bool
    {
        $admin = auth(
            'admin'
        )->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'our_channel.manage'
            );
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::in([
                    self::ACTION_PUBLISH,
                    self::ACTION_UNPUBLISH,
                ]),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function publicationAction(): string
    {
        return (string) $this->validated(
            'action'
        );
    }

    public function auditReason(): ?string
    {
        $reason = trim(
            (string) (
                $this->validated(
                    'reason'
                )
                ?? ''
            )
        );

        return $reason === ''
            ? null
            : $reason;
    }
}
