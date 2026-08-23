<?php

namespace App\Http\Requests\Api\Affiliate;

use Illuminate\Foundation\Http\FormRequest;

class AffiliatePaginationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.max(
                    1,
                    (int) config(
                        'tradex_affiliate_api.max_per_page',
                        50
                    )
                ),
            ],
        ];
    }
}
