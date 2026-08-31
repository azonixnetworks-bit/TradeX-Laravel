<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPreparedUsdtEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tx_hash' => [
                'required',
                'string',
                'regex:/^[A-Fa-f0-9]{64}$/',
            ],
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,10}(?:\.\d{1,6})?$/',
            ],
            'sender_address' => ['nullable', 'string', 'max:100'],
            'proof_file' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:'.max(
                    1,
                    (int) config(
                        'tradex_payments.local_bank.proof_max_kb',
                        5120
                    )
                ),
            ],
        ];
    }
}
