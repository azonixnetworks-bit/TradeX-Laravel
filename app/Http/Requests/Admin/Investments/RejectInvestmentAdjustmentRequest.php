<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class RejectInvestmentAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
