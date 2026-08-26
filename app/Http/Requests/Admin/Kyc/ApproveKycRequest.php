<?php

namespace App\Http\Requests\Admin\Kyc;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class ApproveKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('kyc.view')
            && $admin->hasPermission('kyc.approve');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'internal_notes' => $this->filled('internal_notes')
                ? trim((string) $this->input('internal_notes'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function internalNotes(): ?string
    {
        $value = $this->validated('internal_notes');

        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
