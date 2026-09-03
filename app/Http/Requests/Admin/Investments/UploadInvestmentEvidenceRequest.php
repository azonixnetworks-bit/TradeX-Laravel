<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class UploadInvestmentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf,csv,txt',
            ],
            'account_reference' => ['nullable', 'string', 'max:191'],
            'source_reference' => ['nullable', 'string', 'max:191'],
        ];
    }
}
