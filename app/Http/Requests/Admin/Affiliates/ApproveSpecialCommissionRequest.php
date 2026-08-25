<?php

namespace App\Http\Requests\Admin\Affiliates;

use Illuminate\Foundation\Http\FormRequest;

class ApproveSpecialCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_percent' => [
                'required',
                'numeric',
                'gt:20',
                'max:24',
            ],
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
