<?php

namespace App\Http\Requests\Admin\CopyTrading;

use App\Models\AdminUser;
use App\Models\CopyTradeBroker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCopyTradeBrokerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('copy_trading.brokers.manage');
    }

    public function rules(): array
    {
        $broker = $this->route('copyTradeBroker');
        $brokerId = $broker instanceof CopyTradeBroker
            ? $broker->id
            : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('copy_trade_brokers', 'code')
                    ->ignore($brokerId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
        ]);
    }
}
