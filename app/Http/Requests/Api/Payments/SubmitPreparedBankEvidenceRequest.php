<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPreparedBankEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'depositor_name' => ['required', 'string', 'min:2', 'max:120'],
            'bank_reference' => ['required', 'string', 'min:2', 'max:191'],
            'deposit_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'proof_file' => [
                'required',
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
