<?php

namespace App\Http\Requests\Admin\Investments;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UploadSignedInvestmentAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'investments.agreements.manage'
            );
    }

    public function rules(): array
    {
        return [
            'signed_document' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:'.(int) config(
                    'tradex_investments.agreement.max_signed_document_kb',
                    10240
                ),
            ],
            'signed_at' => [
                'required',
                'date',
                'before_or_equal:now',
            ],
        ];
    }
}
