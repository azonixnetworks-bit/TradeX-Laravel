<?php

namespace App\Http\Requests\Admin\Investments;

use Illuminate\Foundation\Http\FormRequest;

class StartInvestmentEarlyTerminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
