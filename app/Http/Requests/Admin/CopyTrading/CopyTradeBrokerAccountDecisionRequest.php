<?php

namespace App\Http\Requests\Admin\CopyTrading;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class CopyTradeBrokerAccountDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('copy_trading.view');
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
            'portal_reference' => [
                'nullable',
                'string',
                'max:500',
            ],
            'evidence' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:'.(int) config(
                    'tradex_copy_trading.review_evidence_max_kb',
                    10240
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'portal_reference' => $this->filled('portal_reference')
                ? trim((string) $this->input('portal_reference'))
                : null,
        ]);
    }
}
