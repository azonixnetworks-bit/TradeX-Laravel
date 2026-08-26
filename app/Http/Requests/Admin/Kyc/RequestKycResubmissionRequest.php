<?php

namespace App\Http\Requests\Admin\Kyc;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class RequestKycResubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('kyc.view')
            && $admin->hasPermission('kyc.request_resubmission');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason', '')),
            'internal_notes' => $this->filled('internal_notes')
                ? trim((string) $this->input('internal_notes'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function reason(): string
    {
        return (string) $this->validated('reason');
    }

    public function internalNotes(): ?string
    {
        $value = $this->validated('internal_notes');

        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
