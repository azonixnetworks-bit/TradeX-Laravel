<?php

namespace App\Http\Requests\Api\Affiliate;

use App\Models\AffiliateQualifiedSale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AffiliateQualifiedSaleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    AffiliateQualifiedSale::STATUS_CLEARED,
                    AffiliateQualifiedSale::STATUS_PAYMENT_REVERSED,
                ]),
            ],

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
