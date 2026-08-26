<?php

namespace App\Http\Requests\Admin\Settings;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'settings.view'
            )
            && $admin->hasPermission(
                'settings.manage'
            );
    }

    protected function prepareForValidation(): void
    {
        $supportEmail = $this->input(
            'support_email'
        );

        $supportWhatsapp = $this->input(
            'support_whatsapp'
        );

        $notice = $this->input(
            'maintenance_notice'
        );

        $this->merge([
            'platform_name' =>
                trim(
                    (string) $this->input(
                        'platform_name'
                    )
                ),

            'support_email' =>
                is_scalar($supportEmail)
                    ? (
                        trim(
                            (string) $supportEmail
                        ) !== ''
                            ? strtolower(
                                trim(
                                    (string) $supportEmail
                                )
                            )
                            : null
                    )
                    : null,

            'support_whatsapp' =>
                is_scalar($supportWhatsapp)
                    ? (
                        trim(
                            (string) $supportWhatsapp
                        ) !== ''
                            ? trim(
                                (string) $supportWhatsapp
                            )
                            : null
                    )
                    : null,

            'maintenance_notice_enabled' =>
                $this->boolean(
                    'maintenance_notice_enabled'
                ),

            'maintenance_notice' =>
                is_scalar($notice)
                    ? (
                        trim(
                            (string) $notice
                        ) !== ''
                            ? trim(
                                (string) $notice
                            )
                            : null
                    )
                    : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'platform_name' => [
                'required',
                'string',
                'min:2',
                'max:80',
            ],

            'support_email' => [
                'nullable',
                'email:rfc',
                'max:190',
            ],

            'support_whatsapp' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+[1-9]\d{7,14}$/',
            ],

            'maintenance_notice_enabled' => [
                'required',
                'boolean',
            ],

            'maintenance_notice' => [
                Rule::requiredIf(
                    fn (): bool =>
                        $this->boolean(
                            'maintenance_notice_enabled'
                        )
                ),
                'nullable',
                'string',
                'max:500',
            ],

            'versions' => [
                'required',
                'array',
            ],

            'versions.platform_name' => [
                'required',
                'integer',
                'min:0',
            ],

            'versions.support_email' => [
                'required',
                'integer',
                'min:0',
            ],

            'versions.support_whatsapp' => [
                'required',
                'integer',
                'min:0',
            ],

            'versions.maintenance_notice_enabled' => [
                'required',
                'integer',
                'min:0',
            ],

            'versions.maintenance_notice' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}
