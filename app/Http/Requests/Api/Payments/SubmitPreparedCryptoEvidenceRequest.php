<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPreparedCryptoEvidenceRequest
    extends FormRequest
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
                'min:8',
                'max:191',
                'regex:/^[A-Za-z0-9:_-]+$/',
            ],
            'amount' => [
                'required',
                'string',
                'max:80',
            ],
            'sender_address' => [
                'nullable',
                'string',
                'max:191',
            ],
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
