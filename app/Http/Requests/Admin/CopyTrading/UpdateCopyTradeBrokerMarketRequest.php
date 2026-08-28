<?php

namespace App\Http\Requests\Admin\CopyTrading;

use App\Models\AdminUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCopyTradeBrokerMarketRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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
