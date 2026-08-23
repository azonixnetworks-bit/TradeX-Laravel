<?php

namespace App\Http\Requests\Admin\TradeIdeas;

use App\Models\AdminUser;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewTradeIdeaShareCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth(
            'admin'
        )->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'trade_ideas.view'
            );
    }

    public function rules(): array
    {
        return [
            'variant' => [
                'required',
                'string',
                Rule::in(
                    app(
                        TradeIdeaShareCardService::class
                    )->variants()
                ),
            ],

            'referral_code' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],

            'affiliate_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                'starts_with:https://',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'variant' => strtolower(
                trim(
                    (string) $this->input(
                        'variant',
                        TradeIdeaShareCardService::VARIANT_PORTRAIT
                    )
                )
            ),

            'referral_code' => $this->filled(
                'referral_code'
            )
                ? trim(
                    (string) $this->input(
                        'referral_code'
                    )
                )
                : null,

            'affiliate_url' => $this->filled(
                'affiliate_url'
            )
                ? trim(
                    (string) $this->input(
                        'affiliate_url'
                    )
                )
                : null,
        ]);
    }
}
