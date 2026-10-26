<?php

namespace App\Http\Requests\Api\Affiliate;

use Illuminate\Foundation\Http\FormRequest;

class AffiliateEarningsSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'months' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
