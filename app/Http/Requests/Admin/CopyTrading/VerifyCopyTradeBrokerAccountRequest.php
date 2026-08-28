<?php

namespace App\Http\Requests\Admin\CopyTrading;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class VerifyCopyTradeBrokerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('copy_trading.accounts.verify');
    }

    public function rules(): array
    {
        return [
            'partner_attribution_confirmed' => [
                'required',
                'accepted',
            ],
            'live_account_confirmed' => [
                'required',
                'accepted',
            ],
            'verified_currency' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z]{3,10}$/',
            ],
            'portal_reference' => [
                'nullable',
                'string',
                'max:500',
                'required_without:evidence',
            ],
            'evidence' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:'.(int) config(
                    'tradex_copy_trading.review_evidence_max_kb',
                    10240
                ),
                'required_without:portal_reference',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'verified_currency' => strtoupper(
                trim((string) $this->input('verified_currency'))
            ),
            'portal_reference' => $this->filled('portal_reference')
                ? trim((string) $this->input('portal_reference'))
                : null,
            'reason' => $this->filled('reason')
                ? trim((string) $this->input('reason'))
                : null,
        ]);
    }
}
