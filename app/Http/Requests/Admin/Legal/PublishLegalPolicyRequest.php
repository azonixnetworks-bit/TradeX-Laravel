<?php

namespace App\Http\Requests\Admin\Legal;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class PublishLegalPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'legal.view'
            )
            && $admin->hasPermission(
                'legal.publish'
            );
    }

    protected function prepareForValidation(): void
    {
        $effectiveAt =
            $this->input(
                'effective_at'
            );

        $this->merge([
            'effective_at' =>
                is_scalar($effectiveAt)
                && trim(
                    (string) $effectiveAt
                ) !== ''
                    ? trim(
                        (string) $effectiveAt
                    )
                    : null,

            'publish_confirmation' =>
                filter_var(
                    $this->input(
                        'publish_confirmation',
                        false
                    ),
                    FILTER_VALIDATE_BOOL
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'effective_at' => [
                'nullable',
                'date',
            ],

            'revision_version' => [
                'required',
                'integer',
                'min:0',
            ],

            'publish_confirmation' => [
                'required',
                'accepted',
            ],
        ];
    }
}
