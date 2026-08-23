<?php

namespace App\Http\Requests\Api\Affiliate;

use App\Models\AffiliateCommission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AffiliateCommissionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_source' => [
                'sometimes',
                'string',
                Rule::in([
                    AffiliateCommission::RATE_SOURCE_STANDARD,
                    AffiliateCommission::RATE_SOURCE_SPECIAL,
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
