<?php

namespace App\Http\Requests\Admin\CopyTrading;

use App\Models\AdminUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCopyTradeBrokerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('copy_trading.brokers.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_\-]+$/',
                'unique:copy_trade_brokers,code',
            ],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'market_category' => [
                'required',
                Rule::in((array) config(
                    'tradex_copy_trading.market_categories',
                    []
                )),
            ],
            'referral_url' => [
                'required',
                'string',
                'max:2048',
                'url',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if (
                        strtolower((string) parse_url((string) $value, PHP_URL_SCHEME))
                        !== 'https'
                    ) {
                        $fail('The referral URL must use HTTPS.');
                    }
                },
            ],
            'required_currency' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z]{3,10}$/',
            ],
            'market_is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'market_category' => strtoupper(
                trim((string) $this->input('market_category'))
            ),
            'required_currency' => strtoupper(
                trim((string) $this->input(
                    'required_currency',
                    config('tradex_copy_trading.required_currency', 'USD')
                ))
            ),
            'referral_url' => trim((string) $this->input('referral_url')),
        ]);
    }
}
