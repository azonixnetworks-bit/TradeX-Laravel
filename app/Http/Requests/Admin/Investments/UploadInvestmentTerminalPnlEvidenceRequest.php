<?php

namespace App\Http\Requests\Admin\Investments;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UploadInvestmentTerminalPnlEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('investments.evidence.manage');
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
            'account_reference' => [
                'required',
                'string',
                'max:191',
            ],
            'source_reference' => [
                'required',
                'string',
                'max:191',
            ],
        ];
    }
}
