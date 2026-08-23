<?php

namespace App\Http\Requests\Api\Payments;

use App\Models\PaymentOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (
            [
                'provider',
                'status',
            ] as $field
        ) {
            if ($this->has($field)) {
                $this->merge([
                    $field => strtoupper(
                        trim(
                            (string) $this->input(
                                $field
                            )
                        )
                    ),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'provider' => [
                'sometimes',
                'string',
                Rule::in([
                    PaymentOrder::PROVIDER_PAYPAL,
                    PaymentOrder::PROVIDER_LOCAL_BANK,
                    PaymentOrder::PROVIDER_USDT_TRC20,
                ]),
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    PaymentOrder::STATUS_CREATED,
                    PaymentOrder::STATUS_AWAITING_USER_ACTION,
                    PaymentOrder::STATUS_PENDING_REVIEW,
                    PaymentOrder::STATUS_PROCESSING,
                    PaymentOrder::STATUS_CLEARED,
                    PaymentOrder::STATUS_FAILED,
                    PaymentOrder::STATUS_CANCELLED,
                    PaymentOrder::STATUS_PAYMENT_REVERSED,
                ]),
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.max(
                    1,
                    (int) config(
                        'tradex_payments.pagination.max_per_page',
                        50
                    )
                ),
            ],
        ];
    }
}
